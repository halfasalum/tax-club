import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class SpacesScreen extends StatelessWidget {
  const SpacesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Community'))),
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('community/spaces')),
        isEmpty: (spaces) => spaces.isEmpty,
        empty: EmptyPanel(message: context.tr('No community spaces yet.'), icon: Icons.forum_outlined),
        builder: (context, spaces, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Tile(
                color: Brand.yellowWash,
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Icon(Icons.shield_outlined),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(context.tr('Be kind and stay safe: never share your phone number, address or social media. Report anything that worries you.')),
                  ),
                ]),
              ),
              const SizedBox(height: 12),
              for (final space in spaces)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Tile(
                    onTap: () => context.push('/community/${space.str('slug')}', extra: space.str('name')),
                    child: Row(children: [
                      CircleAvatar(
                        backgroundColor: Brand.yellow,
                        foregroundColor: Brand.ink,
                        child: Icon(switch (space.str('type')) {
                          'national' => Icons.public,
                          'institution' => Icons.school_outlined,
                          'group' => Icons.groups_outlined,
                          'event' => Icons.event_outlined,
                          'competition' => Icons.emoji_events_outlined,
                          _ => Icons.tag,
                        }),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(space.str('name'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                          if (space.strOrNull('description') != null)
                            Text(space.str('description'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.slate)),
                          const SizedBox(height: 4),
                          Text(
                            '${labelOf(context, 'space_types', space.str('type'))} · ${context.trPlural(':count post|:count posts', space.integer('posts_count'))}',
                            style: const TextStyle(fontSize: 12, color: Brand.slate),
                          ),
                        ]),
                      ),
                      const Icon(Icons.chevron_right),
                    ]),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class SpaceScreen extends StatefulWidget {
  const SpaceScreen({super.key, required this.slug, this.name});

  final String slug;
  final String? name;

  @override
  State<SpaceScreen> createState() => _SpaceScreenState();
}

class _SpaceScreenState extends State<SpaceScreen> {
  final _list = GlobalKey<PagedListState<Map<String, dynamic>>>();

  Future<void> _write() async {
    final posted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _Composer(slug: widget.slug),
    );
    if (posted == true) _list.currentState?.refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.name ?? context.tr('Community'))),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: Brand.ink,
        foregroundColor: Colors.white,
        onPressed: _write,
        icon: const Icon(Icons.edit),
        label: Text(context.tr('New post')),
      ),
      body: PagedList<Map<String, dynamic>>(
        key: _list,
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
        load: (page) => context.api.paged('community/spaces/${widget.slug}/posts', (row) => row, query: {'page': page}),
        empty: EmptyPanel(message: context.tr('No posts yet. Start the conversation!'), icon: Icons.chat_bubble_outline),
        itemBuilder: (context, post, refresh) => PostCard(
          post: post,
          onTap: () async {
            await context.push('/community/post/${post.str('post_id')}');
            refresh();
          },
          onChanged: refresh,
        ),
      ),
    );
  }
}

class PostCard extends StatelessWidget {
  const PostCard({super.key, required this.post, this.onTap, this.onChanged, this.isComment = false});

  final Map<String, dynamic> post;
  final VoidCallback? onTap;
  final VoidCallback? onChanged;
  final bool isComment;

  @override
  Widget build(BuildContext context) {
    final mine = post.flag('is_mine');
    return Tile(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(16, 12, 4, 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          CircleAvatar(radius: 16, backgroundColor: Brand.mist, child: Text(post.str('author', '?').characters.first.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w700))),
          const SizedBox(width: 8),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(mine ? context.tr('You') : post.str('author'), style: const TextStyle(fontWeight: FontWeight.w600)),
              Text(context.ago(post.date('created_at')), style: const TextStyle(fontSize: 12, color: Brand.slate)),
            ]),
          ),
          if (post.str('status') == 'held') Pill.tone(context.tr('Waiting for a moderator'), 'warn'),
          PopupMenuButton<String>(
            onSelected: (action) async {
              if (action == 'delete') {
                if (!await confirm(context, context.tr('Delete this post?'), action: context.tr('Delete'), danger: true)) return;
                if (!context.mounted) return;
                final result = await runAction(context, () => context.api.delete('community/posts/${post.str('post_id')}'), success: context.tr('Deleted.'));
                if (result != null) onChanged?.call();
              } else {
                await showReportSheet(context, type: 'post', id: post.str('post_id'));
              }
            },
            itemBuilder: (context) => [
              if (mine) PopupMenuItem(value: 'delete', child: Text(context.tr('Delete'))),
              if (!mine) PopupMenuItem(value: 'report', child: Text(context.tr('Report'))),
            ],
          ),
        ]),
        const SizedBox(height: 8),
        Padding(
          padding: const EdgeInsets.only(right: 12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (post.strOrNull('title') != null) Text(post.str('title'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            Text(post.str('body'), maxLines: onTap == null ? null : 6, overflow: onTap == null ? null : TextOverflow.ellipsis, style: const TextStyle(height: 1.4)),
            if (!isComment && onTap != null) ...[
              const SizedBox(height: 8),
              Row(children: [
                const Icon(Icons.chat_bubble_outline, size: 16, color: Brand.slate),
                const SizedBox(width: 4),
                Text(context.trPlural(':count comment|:count comments', post.integer('comments_count')), style: const TextStyle(color: Brand.slate, fontSize: 13)),
              ]),
            ],
          ]),
        ),
      ]),
    );
  }
}

class PostScreen extends StatefulWidget {
  const PostScreen({super.key, required this.id});

  final String id;

  @override
  State<PostScreen> createState() => _PostScreenState();
}

class _PostScreenState extends State<PostScreen> {
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();
  final _comment = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final body = _comment.text.trim();
    if (body.isEmpty) return;
    setState(() => _sending = true);
    final result = await runAction(context, () => context.api.post('community/posts/${widget.id}/comments', {'body': body}), showServerMessage: true);
    if (mounted) setState(() => _sending = false);
    if (result != null) {
      _comment.clear();
      _loader.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Post'))),
      body: Column(children: [
        Expanded(
          child: Loader<Map<String, dynamic>>(
            key: _loader,
            load: () async => ((await context.api.get('community/posts/${widget.id}')) as Map).cast<String, dynamic>(),
            builder: (context, post, reload) => RefreshIndicator(
              onRefresh: reload,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  PostCard(post: post, onChanged: () => context.pop()),
                  const SizedBox(height: 12),
                  SectionTitle(context.trPlural(':count comment|:count comments', post.list('comments').length)),
                  for (final comment in post.list('comments'))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8, left: 16),
                      child: PostCard(post: comment, isComment: true, onChanged: reload),
                    ),
                ],
              ),
            ),
          ),
        ),
        SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
            decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: Brand.line))),
            child: Row(children: [
              Expanded(
                child: TextField(
                  controller: _comment,
                  minLines: 1,
                  maxLines: 4,
                  maxLength: 3000,
                  decoration: InputDecoration(hintText: context.tr('Write a comment'), counterText: '', isDense: true),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                tooltip: context.tr('Send'),
                onPressed: _sending ? null : _send,
                icon: _sending ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send),
              ),
            ]),
          ),
        ),
      ]),
    );
  }
}

class _Composer extends StatefulWidget {
  const _Composer({required this.slug});

  final String slug;

  @override
  State<_Composer> createState() => _ComposerState();
}

class _ComposerState extends State<_Composer> {
  final _title = TextEditingController();
  final _body = TextEditingController();

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(context.tr('New post'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          TextField(controller: _title, maxLength: 200, decoration: InputDecoration(labelText: context.tr('Title (optional)'), counterText: '')),
          const SizedBox(height: 12),
          TextField(controller: _body, minLines: 4, maxLines: 10, maxLength: 5000, decoration: InputDecoration(labelText: context.tr('What would you like to share?'))),
          Text(context.tr('Posts with phone numbers, emails or social media names wait for a moderator.'), style: const TextStyle(color: Brand.slate, fontSize: 12)),
          const SizedBox(height: 12),
          BusyButton(
            label: context.tr('Post'),
            onPressed: () async {
              if (_body.text.trim().isEmpty) return;
              final result = await runAction(
                context,
                () => context.api.post('community/spaces/${widget.slug}/posts', {'title': _title.text.trim(), 'body': _body.text.trim()}),
                showServerMessage: true,
              );
              if (result != null && context.mounted) Navigator.pop(context, true);
            },
          ),
        ],
      ),
    );
  }
}

/// Report content or a person to the safety team (SAF-REQ01).
Future<void> showReportSheet(BuildContext context, {required String type, required String id}) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _ReportSheet(type: type, id: id),
  );
}

class _ReportSheet extends StatefulWidget {
  const _ReportSheet({required this.type, required this.id});

  final String type;
  final String id;

  @override
  State<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends State<_ReportSheet> {
  final _details = TextEditingController();
  String? _category;

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final categories = optionsOf(context, 'report_categories');
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(context.tr('Report'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(context.tr('Your report is private. The person will not know who reported them.'), style: const TextStyle(color: Brand.slate)),
            const SizedBox(height: 8),
            RadioGroup<String>(
              groupValue: _category,
              onChanged: (value) => setState(() => _category = value),
              child: Column(children: [
                for (final category in categories.entries)
                  RadioListTile<String>(contentPadding: EdgeInsets.zero, dense: true, value: category.key, title: Text(category.value)),
              ]),
            ),
            TextField(controller: _details, maxLines: 3, maxLength: 2000, decoration: InputDecoration(labelText: context.tr('Anything else we should know? (optional)'))),
            const SizedBox(height: 8),
            BusyButton(
              label: context.tr('Send report'),
              onPressed: _category == null
                  ? null
                  : () async {
                      final result = await runAction(
                        context,
                        () => context.api.post('reports', {'type': widget.type, 'id': widget.id, 'category': _category, 'details': _details.text.trim()}),
                        showServerMessage: true,
                      );
                      if (result != null && context.mounted) Navigator.pop(context);
                    },
            ),
            const SizedBox(height: 8),
            Text(
              context.tr('If someone is in danger right now, tell your patron, a teacher or a parent straight away.'),
              style: const TextStyle(color: Brand.slate, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
