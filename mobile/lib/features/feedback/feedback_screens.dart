import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class FeedbackListScreen extends StatelessWidget {
  const FeedbackListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final loader = GlobalKey<LoaderState<List<Map<String, dynamic>>>>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Feedback'))),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: Brand.ink,
        foregroundColor: Colors.white,
        onPressed: () async {
          final sent = await context.push<bool>('/feedback/new');
          if (sent == true) loader.currentState?.reload();
        },
        icon: const Icon(Icons.add),
        label: Text(context.tr('Send feedback')),
      ),
      body: Loader<List<Map<String, dynamic>>>(
        key: loader,
        load: () async => listOf(await context.api.get('feedback')),
        isEmpty: (rows) => rows.isEmpty,
        empty: EmptyPanel(message: context.tr('Tell TRA what you think, report a problem or raise a concern. We read every message.'), icon: Icons.feedback_outlined),
        builder: (context, rows, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            itemCount: rows.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final item = rows[index];
              return Tile(
                onTap: () => context.push('/feedback/${item.str('reference')}'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Text(item.str('reference'), style: const TextStyle(color: Brand.slate, fontSize: 12)),
                    const Spacer(),
                    Pill.tone(item.str('status_label'), item.str('status') == 'responded' ? 'good' : toneOf(item.str('status'))),
                  ]),
                  const SizedBox(height: 6),
                  Text(item.str('subject'), style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('${labelOf(context, 'feedback_categories', item.str('category'))} · ${context.date(item.date('created_at'))}', style: const TextStyle(color: Brand.slate, fontSize: 12)),
                ]),
              );
            },
          ),
        ),
      ),
    );
  }
}

class NewFeedbackScreen extends StatefulWidget {
  const NewFeedbackScreen({super.key});

  @override
  State<NewFeedbackScreen> createState() => _NewFeedbackScreenState();
}

class _NewFeedbackScreenState extends State<NewFeedbackScreen> {
  final _form = GlobalKey<FormState>();
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
    if (!_form.currentState!.validate()) return;
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('feedback', {'category': _category, 'subject': _subject.text.trim(), 'message': _message.text.trim()});
      if (!mounted) return;
      showMessage(context, context.tr('Thank you. Your reference is :reference.', {'reference': dataOf(envelope).str('reference')}));
      context.pop(true);
    } on ApiException catch (error) {
      setState(() => _error = error);
      if (error.errors.isEmpty && mounted) showMessage(context, errorText(context, error), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Send feedback'))),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(context.tr('What is it about?'), style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final category in optionsOf(context, 'feedback_categories').entries)
                ChoiceChip(label: Text(category.value), selected: _category == category.key, onSelected: (_) => setState(() => _category = category.key)),
            ]),
            if (_category == 'concern') ...[
              const SizedBox(height: 12),
              Tile(color: Brand.yellowWash, child: Text(context.tr('Concerns about safety go straight to a safeguarding officer. If someone is in danger now, tell your patron, a teacher or a parent.'))),
            ],
            const SizedBox(height: 16),
            TextFormField(
              controller: _subject,
              maxLength: 200,
              decoration: InputDecoration(labelText: context.tr('Subject'), errorText: _error?.field('subject')),
              validator: (value) => (value ?? '').trim().length < 4 ? context.tr('Use at least 4 characters.') : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _message,
              minLines: 5,
              maxLines: 12,
              maxLength: 5000,
              decoration: InputDecoration(labelText: context.tr('Message'), alignLabelWithHint: true, errorText: _error?.field('message')),
              validator: (value) => (value ?? '').trim().length < 10 ? context.tr('Use at least 10 characters.') : null,
            ),
            const SizedBox(height: 16),
            BusyButton(label: context.tr('Send'), icon: Icons.send, onPressed: _send),
          ],
        ),
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
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();
  final _reply = TextEditingController();

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.reference)),
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: () async => ((await context.api.get('feedback/${widget.reference}')) as Map).cast<String, dynamic>(),
        builder: (context, item, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(children: [
                Pill(labelOf(context, 'feedback_categories', item.str('category'))),
                const Spacer(),
                Pill.tone(item.str('status_label'), item.str('status') == 'responded' ? 'good' : toneOf(item.str('status'))),
              ]),
              const SizedBox(height: 10),
              Text(item.str('subject'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              Text(context.dateTime(item.date('created_at')), style: const TextStyle(color: Brand.slate)),
              const SizedBox(height: 12),
              Tile(child: Paragraphs(item.str('message'))),
              if (item.strOrNull('response') != null) ...[
                const SizedBox(height: 16),
                SectionTitle(context.tr('Reply from TRA')),
                Tile(color: Brand.yellowWash, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Paragraphs(item.str('response')),
                  if (item.date('responded_at') != null) Text(context.dateTime(item.date('responded_at')), style: const TextStyle(color: Brand.slate, fontSize: 12)),
                ])),
              ],
              for (final step in item.list('history').where((step) => step.strOrNull('note') != null)) ...[
                const SizedBox(height: 8),
                Tile(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(context.tr('You wrote'), style: const TextStyle(fontWeight: FontWeight.w600)),
                  Paragraphs(step.str('note')),
                  Text(context.dateTime(step.date('at')), style: const TextStyle(color: Brand.slate, fontSize: 12)),
                ])),
              ],
              if (item.str('status') != 'closed') ...[
                const SizedBox(height: 20),
                TextField(controller: _reply, minLines: 2, maxLines: 6, maxLength: 5000, decoration: InputDecoration(labelText: context.tr('Add a message'))),
                BusyButton(
                  label: context.tr('Send'),
                  onPressed: () async {
                    if (_reply.text.trim().isEmpty) return;
                    final result = await runAction(context, () => context.api.post('feedback/${widget.reference}/messages', {'message': _reply.text.trim()}), showServerMessage: true);
                    if (result != null) {
                      _reply.clear();
                      reload();
                    }
                  },
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
