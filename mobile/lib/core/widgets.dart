import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'i18n.dart';
import 'session.dart';
import 'theme.dart';

extension SessionContext on BuildContext {
  Session get session => read<Session>();
  Api get api => read<Session>().api;
}

/// Turns an error into words for the member.
String errorText(BuildContext context, Object error) {
  if (error is ApiException) {
    return switch (error.message) {
      'offline' => context.tr(
        'No internet connection. Check your data or Wi-Fi and try again.',
      ),
      'timeout' => context.tr(
        'The server is taking too long. Please try again.',
      ),
      'server_error' => context.tr('Something went wrong. Please try again.'),
      _ => error.details,
    };
  }
  return context.tr('Something went wrong. Please try again.');
}

void showMessage(BuildContext context, String message, {bool error = false}) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  messenger?.hideCurrentSnackBar();
  messenger?.showSnackBar(
    SnackBar(
      content: Text(message),
      backgroundColor: error ? Brand.danger : Brand.charcoal,
    ),
  );
}

/// Runs an API action, shows the server's message (or the error) and
/// returns the envelope, or null when it failed.
Future<T?> runAction<T>(
  BuildContext context,
  Future<T> Function() action, {
  String? success,
  bool showServerMessage = false,
}) async {
  try {
    final result = await action();
    if (!context.mounted) return result;
    final serverMessage = result is Map ? result['message'] as String? : null;
    final text = success ?? (showServerMessage ? serverMessage : null);
    if (text != null && text.isNotEmpty) showMessage(context, text);
    return result;
  } catch (error) {
    if (context.mounted) {
      showMessage(context, errorText(context, error), error: true);
    }
    return null;
  }
}

Future<void> openLink(BuildContext context, String? url) async {
  if (url == null || url.isEmpty) return;
  final uri = Uri.tryParse(
    url.startsWith('http') ||
            url.startsWith('mailto:') ||
            url.startsWith('tel:')
        ? url
        : 'https://$url',
  );
  if (uri == null ||
      !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
    if (context.mounted) {
      showMessage(context, context.tr('Could not open the link.'), error: true);
    }
  }
}

/// Loads data once, with loading, error (retry) and pull-to-refresh states.
class Loader<T> extends StatefulWidget {
  const Loader({
    super.key,
    required this.load,
    required this.builder,
    this.isEmpty,
    this.empty,
  });

  final Future<T> Function() load;
  final Widget Function(
    BuildContext context,
    T data,
    Future<void> Function() reload,
  )
  builder;
  final bool Function(T data)? isEmpty;
  final Widget? empty;

  @override
  State<Loader<T>> createState() => LoaderState<T>();
}

class LoaderState<T> extends State<Loader<T>> {
  T? _data;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    setState(() {
      _loading = _data == null;
      _error = null;
    });
    try {
      final data = await widget.load();
      if (mounted) setState(() => _data = data);
    } catch (error) {
      if (mounted) setState(() => _error = error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    if (_error != null && _data == null) {
      return ErrorPanel(message: errorText(context, _error!), onRetry: reload);
    }

    final data = _data as T;

    if (widget.isEmpty?.call(data) ?? false) {
      return RefreshIndicator(
        onRefresh: reload,
        child: ListView(
          children: [
            const SizedBox(height: 80),
            widget.empty ??
                EmptyPanel(message: context.tr('Nothing here yet.')),
          ],
        ),
      );
    }

    return widget.builder(context, data, reload);
  }
}

/// An endless list for paginated endpoints.
class PagedList<T> extends StatefulWidget {
  const PagedList({
    super.key,
    required this.load,
    required this.itemBuilder,
    this.empty,
    this.header,
    this.padding = const EdgeInsets.all(16),
    this.separator = 10,
  });

  final Future<Paged<T>> Function(int page) load;
  final Widget Function(
    BuildContext context,
    T item,
    Future<void> Function() refresh,
  )
  itemBuilder;
  final Widget? empty;
  final Widget? header;
  final EdgeInsets padding;
  final double separator;

  @override
  State<PagedList<T>> createState() => PagedListState<T>();
}

class PagedListState<T> extends State<PagedList<T>> {
  final _items = <T>[];
  int _page = 0;
  bool _hasMore = true;
  bool _loading = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    _page = 0;
    _hasMore = true;
    _items.clear();
    await _next();
  }

  Future<void> _next() async {
    if (_loading || !_hasMore) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await widget.load(_page + 1);
      if (!mounted) return;
      setState(() {
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _items.addAll(page.items);
      });
    } catch (error) {
      if (mounted) setState(() => _error = error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_items.isEmpty && _loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_items.isEmpty && _error != null) {
      return ErrorPanel(message: errorText(context, _error!), onRetry: refresh);
    }

    final extra = (widget.header != null ? 1 : 0);
    final showEmpty = _items.isEmpty && !_loading;

    return RefreshIndicator(
      onRefresh: refresh,
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.extentAfter < 400) _next();
          return false;
        },
        child: ListView.separated(
          padding: widget.padding,
          physics: const AlwaysScrollableScrollPhysics(),
          itemCount:
              extra +
              (showEmpty ? 1 : _items.length) +
              (_hasMore && _items.isNotEmpty ? 1 : 0),
          separatorBuilder: (_, _) => SizedBox(height: widget.separator),
          itemBuilder: (context, index) {
            if (widget.header != null && index == 0) return widget.header!;
            final i = index - extra;
            if (showEmpty) {
              return widget.empty ??
                  EmptyPanel(message: context.tr('Nothing here yet.'));
            }
            if (i >= _items.length) {
              return Padding(
                padding: const EdgeInsets.all(16),
                child: Center(
                  child: _error != null
                      ? TextButton(
                          onPressed: _next,
                          child: Text(context.tr('Try again')),
                        )
                      : const CircularProgressIndicator(),
                ),
              );
            }
            return widget.itemBuilder(context, _items[i], refresh);
          },
        ),
      ),
    );
  }
}

class ErrorPanel extends StatelessWidget {
  const ErrorPanel({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 48, color: Brand.slate),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: Text(context.tr('Try again')),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class EmptyPanel extends StatelessWidget {
  const EmptyPanel({
    super.key,
    required this.message,
    this.icon = Icons.inbox_outlined,
    this.action,
  });

  final String message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Column(
        children: [
          Icon(icon, size: 48, color: Brand.slate),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge
                ?.copyWith(color: Brand.slate),
          ),
          if (action != null) ...[const SizedBox(height: 16), action!],
        ],
      ),
    );
  }
}

/// A small coloured label such as "Open" or "Waitlisted".
class Pill extends StatelessWidget {
  const Pill(
    this.text, {
    super.key,
    this.color = Brand.slate,
    this.filled = false,
  });

  final String text;
  final Color color;
  final bool filled;

  factory Pill.tone(String text, String tone) => Pill(
    text,
    color: switch (tone) {
      'good' => Brand.success,
      'bad' => Brand.danger,
      'info' => Brand.info,
      'warn' => const Color(0xFFB45309),
      _ => Brand.slate,
    },
  );

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: filled ? color : color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: filled ? Colors.white : color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.action, this.onAction});

  final String title;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: Theme.of(context).textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
          ),
          if (action != null)
            TextButton(onPressed: onAction, child: Text(action!)),
        ],
      ),
    );
  }
}

/// A tappable white card.
class Tile extends StatelessWidget {
  const Tile({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(16),
    this.color,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsets padding;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: color,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

class InfoRow extends StatelessWidget {
  const InfoRow({super.key, required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    if (text.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: Brand.slate),
          const SizedBox(width: 8),
          Expanded(
            child: Text(text, style: const TextStyle(color: Brand.charcoal)),
          ),
        ],
      ),
    );
  }
}

/// Plain text that keeps the author's paragraphs.
class Paragraphs extends StatelessWidget {
  const Paragraphs(this.text, {super.key});

  final String? text;

  @override
  Widget build(BuildContext context) {
    if (text == null || text!.trim().isEmpty) return const SizedBox.shrink();
    return SelectableText(
      text!.trim(),
      style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.5),
    );
  }
}

/// A full-width button that shows a spinner while its action runs.
class BusyButton extends StatefulWidget {
  const BusyButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.outlined = false,
  });

  final String label;
  final Future<void> Function()? onPressed;
  final IconData? icon;
  final bool outlined;

  @override
  State<BusyButton> createState() => _BusyButtonState();
}

class _BusyButtonState extends State<BusyButton> {
  bool _busy = false;

  Future<void> _run() async {
    setState(() => _busy = true);
    try {
      await widget.onPressed!();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final onPressed = widget.onPressed == null || _busy ? null : _run;
    final child = _busy
        ? const SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.5),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (widget.icon != null) ...[
                Icon(widget.icon, size: 20),
                const SizedBox(width: 8),
              ],
              Flexible(child: Text(widget.label, textAlign: TextAlign.center)),
            ],
          );

    return SizedBox(
      width: double.infinity,
      child: widget.outlined
          ? OutlinedButton(onPressed: onPressed, child: child)
          : FilledButton(onPressed: onPressed, child: child),
    );
  }
}

/// Asks the member to confirm, returning true when they agree.
Future<bool> confirm(
  BuildContext context,
  String message, {
  String? action,
  bool danger = false,
}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: Text(context.tr('Cancel')),
        ),
        FilledButton(
          style: danger
              ? FilledButton.styleFrom(backgroundColor: Brand.danger)
              : null,
          onPressed: () => Navigator.pop(context, true),
          child: Text(action ?? context.tr('Yes')),
        ),
      ],
    ),
  );
  return result ?? false;
}

/// Picks a value from a list in a bottom sheet.
Future<String?> pickOne(
  BuildContext context,
  String title,
  Map<String, String> options, {
  String? selected,
}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.7,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final entry in options.entries)
                    ListTile(
                      title: Text(entry.value),
                      trailing: entry.key == selected
                          ? const Icon(Icons.check)
                          : null,
                      onTap: () => Navigator.pop(context, entry.key),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
