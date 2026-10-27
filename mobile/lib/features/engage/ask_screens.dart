import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// Ask TRA: the member's questions and the public answers.
class AskScreen extends StatefulWidget {
  const AskScreen({super.key});

  @override
  State<AskScreen> createState() => _AskScreenState();
}

class _AskScreenState extends State<AskScreen> {
  final _mine = GlobalKey<LoaderState<List<Map<String, dynamic>>>>();
  final _answers = GlobalKey<PagedListState<Map<String, dynamic>>>();
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr('Ask TRA')),
          bottom: TabBar(
            labelColor: Brand.ink,
            indicatorColor: Brand.ink,
            tabs: [Tab(text: context.tr('My questions')), Tab(text: context.tr('Answers'))],
          ),
        ),
        floatingActionButton: FloatingActionButton.extended(
          backgroundColor: Brand.ink,
          foregroundColor: Colors.white,
          onPressed: () async {
            final sent = await context.push<bool>('/ask/new');
            if (sent == true) _mine.currentState?.reload();
          },
          icon: const Icon(Icons.add_comment_outlined),
          label: Text(context.tr('Ask a question')),
        ),
        body: TabBarView(children: [
          Loader<List<Map<String, dynamic>>>(
            key: _mine,
            load: () async => listOf(await context.api.get('questions')),
            isEmpty: (rows) => rows.isEmpty,
            empty: EmptyPanel(message: context.tr('Have a tax question? Ask a TRA expert. You will get an answer here and by SMS.'), icon: Icons.help_outline),
            builder: (context, rows, reload) => RefreshIndicator(
              onRefresh: reload,
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                itemCount: rows.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (context, index) => _QuestionCard(question: rows[index]),
              ),
            ),
          ),
          Column(children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: TextField(
                decoration: InputDecoration(hintText: context.tr('Search answers'), prefixIcon: const Icon(Icons.search), isDense: true),
                onChanged: (value) {
                  _debounce?.cancel();
                  _debounce = Timer(const Duration(milliseconds: 450), () {
                    _search = value.trim();
                    _answers.currentState?.refresh();
                  });
                },
              ),
            ),
            Expanded(
              child: PagedList<Map<String, dynamic>>(
                key: _answers,
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                load: (page) => context.api.paged('answers', (row) => row, query: {'search': _search, 'page': page}),
                empty: EmptyPanel(message: context.tr('No published answers match.'), icon: Icons.search_off),
                itemBuilder: (context, answer, _) => Tile(
                  padding: EdgeInsets.zero,
                  child: ExpansionTile(
                    shape: const Border(),
                    title: Text(answer.str('question'), style: const TextStyle(fontWeight: FontWeight.w600)),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    expandedCrossAxisAlignment: CrossAxisAlignment.start,
                    children: [Paragraphs(answer.str('answer'))],
                  ),
                ),
              ),
            ),
          ]),
        ]),
      ),
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({required this.question});

  final Map<String, dynamic> question;

  @override
  Widget build(BuildContext context) {
    final status = question.str('status');
    return Tile(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text(question.str('reference'), style: const TextStyle(color: Brand.slate, fontSize: 12)),
          const Spacer(),
          Pill.tone(labelOf(context, 'question_statuses', status), toneOf(status)),
        ]),
        const SizedBox(height: 6),
        Text(question.str('subject'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        Text(context.date(question.date('created_at')), style: const TextStyle(color: Brand.slate, fontSize: 12)),
        if (question.strOrNull('answer') != null) ...[
          const Divider(height: 24),
          Text(context.tr('Answer from TRA'), style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Paragraphs(question.str('answer')),
        ],
        if (status == 'declined' && question.strOrNull('decline_reason') != null) ...[
          const Divider(height: 24),
          Text(question.str('decline_reason')),
        ],
      ]),
    );
  }
}

class AskQuestionScreen extends StatefulWidget {
  const AskQuestionScreen({super.key});

  @override
  State<AskQuestionScreen> createState() => _AskQuestionScreenState();
}

class _AskQuestionScreenState extends State<AskQuestionScreen> {
  final _form = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _body = TextEditingController();
  bool _allowPublish = true;
  ApiException? _error;

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('questions', {'subject': _subject.text.trim(), 'body': _body.text.trim(), 'allow_publish': _allowPublish});
      if (!mounted) return;
      showMessage(context, context.tr('Sent to TRA. Your reference is :reference.', {'reference': dataOf(envelope).str('reference')}));
      context.pop(true);
    } on ApiException catch (error) {
      setState(() => _error = error);
      if (error.errors.isEmpty && mounted) showMessage(context, errorText(context, error), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Ask a question'))),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(context.tr('A TRA expert will answer. Do not include your TIN, phone number or other personal details.'), style: const TextStyle(color: Brand.slate)),
            const SizedBox(height: 16),
            TextFormField(
              controller: _subject,
              maxLength: 200,
              decoration: InputDecoration(labelText: context.tr('Your question in a few words'), errorText: _error?.field('subject')),
              validator: (value) => (value ?? '').trim().length < 8 ? context.tr('Use at least 8 characters.') : null,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _body,
              minLines: 5,
              maxLines: 12,
              maxLength: 3000,
              decoration: InputDecoration(labelText: context.tr('Explain your question'), alignLabelWithHint: true, errorText: _error?.field('body')),
              validator: (value) => (value ?? '').trim().length < 15 ? context.tr('Use at least 15 characters.') : null,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _allowPublish,
              onChanged: (value) => setState(() => _allowPublish = value),
              title: Text(context.tr('TRA may publish my question and the answer without my name')),
              subtitle: Text(context.tr('This helps other members with the same question.')),
            ),
            const SizedBox(height: 16),
            BusyButton(label: context.tr('Send to TRA'), icon: Icons.send, onPressed: _send),
          ],
        ),
      ),
    );
  }
}
