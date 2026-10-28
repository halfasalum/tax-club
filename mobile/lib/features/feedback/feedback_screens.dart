import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';
import '../auth/auth_screens.dart';

/// "Feedback" (livewire/member/feedback/index.blade.php): the form and the
/// feedback you sent.
class FeedbackListScreen extends StatefulWidget {
  const FeedbackListScreen({super.key});

  @override
  State<FeedbackListScreen> createState() => _FeedbackListScreenState();
}

class _FeedbackListScreenState extends State<FeedbackListScreen> {
  final _loader = GlobalKey<LoaderState<List<Map<String, dynamic>>>>();

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<List<Map<String, dynamic>>>(
        key: _loader,
        load: () async => listOf(await context.api.get('feedback')),
        builder: (context, rows, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              context.tr('Feedback'),
              subtitle: context.tr(
                'Tell us what works, what does not and what you would like to see. Every message gets a reference number so you can follow it.',
              ),
            ),
            const SizedBox(height: 24),
            FeedbackForm(onSent: reload),
            const SizedBox(height: 32),
            SectionHeading(context.tr('Feedback you sent')),
            const SizedBox(height: 14),
            if (rows.isEmpty)
              MistBox(
                context.tr('You have not sent any feedback yet.'),
                color: Colors.white,
              ),
            for (final item in rows)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: WebCard(
                  onTap: () =>
                      context.push('/feedback/${item.str('reference')}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              item.str('reference'),
                              style: const TextStyle(
                                fontFamily: 'IBMPlexMono',
                                fontSize: 13,
                                color: Brand.slate,
                              ),
                            ),
                          ),
                          Tag(
                            item.str('status_label'),
                            dark: item.str('status') == 'responded',
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        item.str('subject'),
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 17,
                        ),
                      ),
                      Text(
                        '${labelOf(context, 'feedback_categories', item.str('category'))} · ${context.date(item.date('created_at'))}',
                        style: const TextStyle(
                          color: Brand.slate,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class FeedbackForm extends StatefulWidget {
  const FeedbackForm({super.key, this.onSent});

  final VoidCallback? onSent;

  @override
  State<FeedbackForm> createState() => _FeedbackFormState();
}

class _FeedbackFormState extends State<FeedbackForm> {
  final _subject = TextEditingController();
  final _message = TextEditingController();
  String _category = 'suggestion';
  ApiException? _error;

  @override
  void dispose() {
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('feedback', {
        'category': _category,
        'subject': _subject.text.trim(),
        'message': _message.text.trim(),
      });
      if (!mounted) return;
      showMessage(
        context,
        '${envelope['message']} ${dataOf(envelope).str('reference')}',
      );
      _subject.clear();
      _message.clear();
      widget.onSent?.call();
    } on ApiException catch (error) {
      setState(() => _error = error);
      if (error.errors.isEmpty && mounted) {
        showMessage(context, errorText(context, error), error: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return WebCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.tr('What is it about?'),
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final category in optionsOf(
                context,
                'feedback_categories',
              ).entries)
                ChoiceChip(
                  label: Text(category.value),
                  selected: _category == category.key,
                  onSelected: (_) => setState(() => _category = category.key),
                ),
            ],
          ),
          const SizedBox(height: 20),
          WebField(
            label: context.tr('Subject'),
            controller: _subject,
            error: _error?.field('subject'),
          ),
          WebField(
            label: context.tr('Message'),
            controller: _message,
            maxLines: 6,
            error: _error?.field('message'),
          ),
          WebButton(context.tr('Send feedback'), onPressed: _send),
        ],
      ),
    );
  }
}

/// Kept for the /feedback/new route.
class NewFeedbackScreen extends StatelessWidget {
  const NewFeedbackScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: MemberPage(
        children: [
          PageTitle(
            context.tr('Send feedback'),
            back: context.tr('Feedback'),
            onBack: () => context.pop(),
          ),
          const SizedBox(height: 24),
          FeedbackForm(onSent: () => context.pop(true)),
        ],
      ),
    );
  }
}

class FeedbackScreen extends StatefulWidget {
  const FeedbackScreen({super.key, required this.reference});

  final String reference;

  @override
  State<FeedbackScreen> createState() => _FeedbackScreenState();
}

class _FeedbackScreenState extends State<FeedbackScreen> {
  final _reply = TextEditingController();

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () async =>
            ((await context.api.get('feedback/${widget.reference}')) as Map)
                .cast<String, dynamic>(),
        builder: (context, item, reload) => MemberPage(
          onRefresh: reload,
          children: [
            LinkText(
              context.tr('Feedback'),
              onTap: () => context.go('/feedback'),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text(
                    item.str('reference'),
                    style: const TextStyle(
                      fontFamily: 'IBMPlexMono',
                      color: Brand.slate,
                    ),
                  ),
                ),
                Tag(
                  item.str('status_label'),
                  dark: item.str('status') == 'responded',
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              item.str('subject'),
              style: const TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
                height: 1.2,
              ),
            ),
            Text(
              '${labelOf(context, 'feedback_categories', item.str('category'))} · ${context.dateTime(item.date('created_at'))}',
              style: const TextStyle(color: Brand.slate),
            ),
            const SizedBox(height: 20),
            WebCard(child: Paragraphs(item.str('message'))),
            if (item.strOrNull('response') != null) ...[
              const SizedBox(height: 20),
              WebCard(
                highlight: true,
                color: Brand.yellowWash,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('Response from TRA Tax Club'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Paragraphs(item.str('response')),
                    if (item.date('responded_at') != null)
                      Text(
                        context.dateTime(item.date('responded_at')),
                        style: const TextStyle(
                          color: Brand.slate,
                          fontSize: 13,
                        ),
                      ),
                  ],
                ),
              ),
            ],
            for (final step
                in item
                    .list('history')
                    .where((step) => step.strOrNull('note') != null)) ...[
              const SizedBox(height: 10),
              WebCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('You added a message'),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Paragraphs(step.str('note')),
                    Text(
                      context.dateTime(step.date('at')),
                      style: const TextStyle(color: Brand.slate, fontSize: 13),
                    ),
                  ],
                ),
              ),
            ],
            if (item.str('status') != 'closed') ...[
              const SizedBox(height: 24),
              WebCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    WebField(
                      label: context.tr('Add more information'),
                      controller: _reply,
                      maxLines: 4,
                    ),
                    WebButton(
                      context.tr('Send'),
                      onPressed: () async {
                        if (_reply.text.trim().isEmpty) return;
                        final result = await runAction(
                          context,
                          () => context.api.post(
                            'feedback/${widget.reference}/messages',
                            {'message': _reply.text.trim()},
                          ),
                          showServerMessage: true,
                        );
                        if (result != null) {
                          _reply.clear();
                          reload();
                        }
                      },
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
