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

/// "Ask TRA" (livewire/member/ask/index.blade.php): your questions and
/// the published answers.
class AskScreen extends StatefulWidget {
  const AskScreen({super.key});

  @override
  State<AskScreen> createState() => _AskScreenState();
}

class _AskScreenState extends State<AskScreen> {
  final _search = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: ValueKey(_query),
        load: () async {
          final api = context.api;
          final results = await Future.wait([
            api.get('questions'),
            api.getEnvelope('answers', query: {'search': _query}),
          ]);
          return {'mine': results[0], 'answers': (results[1] as Map)['data']};
        },
        builder: (context, data, reload) {
          final mine = data.list('mine');
          final answers = data.list('answers');
          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                context.tr('Ask TRA'),
                subtitle: context.tr(
                  'Ask a TRA tax expert anything about taxes. You get a private answer, usually within a few working days.',
                ),
              ),
              const SizedBox(height: 22),
              WebButton(
                context.tr('Ask a question'),
                kind: BtnKind.brand,
                icon: Icons.add_comment_outlined,
                onPressed: () async {
                  final sent = await context.push<bool>('/ask/new');
                  if (sent == true) reload();
                },
              ),
              const SizedBox(height: 32),
              SectionHeading(context.tr('Your questions')),
              const SizedBox(height: 14),
              if (mine.isEmpty)
                MistBox(
                  context.tr('You have not asked anything yet.'),
                  color: Colors.white,
                ),
              for (final question in mine)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _QuestionCard(question: question),
                ),
              const SizedBox(height: 32),
              SectionHeading(context.tr('Answers from TRA')),
              const SizedBox(height: 14),
              TextField(
                controller: _search,
                textInputAction: TextInputAction.search,
                onSubmitted: (value) => setState(() => _query = value.trim()),
                decoration: InputDecoration(
                  hintText: context.tr('Search answers, e.g. TIN or VAT'),
                  prefixIcon: const Icon(Icons.search),
                ),
              ),
              const SizedBox(height: 14),
              if (answers.isEmpty)
                MistBox(
                  context.tr(
                    _query.isEmpty
                        ? 'Published answers will appear here.'
                        : 'No answers match. Ask your own question!',
                  ),
                  color: Colors.white,
                ),
              for (final answer in answers)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: WebCard(
                    padding: EdgeInsets.zero,
                    child: ExpansionTile(
                      shape: const Border(),
                      tilePadding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 6,
                      ),
                      childrenPadding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
                      expandedCrossAxisAlignment: CrossAxisAlignment.start,
                      title: Text(
                        answer.str('question'),
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 17,
                          height: 1.3,
                        ),
                      ),
                      children: [Paragraphs(answer.str('answer'))],
                    ),
                  ),
                ),
            ],
          );
        },
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
    final answered = question.strOrNull('answer') != null;
    return WebCard(
      highlight: answered,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  question.str('reference'),
                  style: const TextStyle(
                    fontFamily: 'IBMPlexMono',
                    fontSize: 13,
                    color: Brand.slate,
                  ),
                ),
              ),
              Tag(
                labelOf(context, 'question_statuses', status),
                dark: answered,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            question.str('subject'),
            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
          ),
          Text(
            context.date(question.date('created_at')),
            style: const TextStyle(color: Brand.slate, fontSize: 13),
          ),
          if (answered) ...[
            const SizedBox(height: 14),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Brand.yellowWash,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.tr('Answer from TRA'),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 6),
                  Paragraphs(question.str('answer')),
                ],
              ),
            ),
          ],
          if (status == 'declined' &&
              question.strOrNull('decline_reason') != null) ...[
            const SizedBox(height: 14),
            Text(
              context.tr('Why we could not answer'),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            Text(question.str('decline_reason')),
          ],
        ],
      ),
    );
  }
}

class AskQuestionScreen extends StatefulWidget {
  const AskQuestionScreen({super.key});

  @override
  State<AskQuestionScreen> createState() => _AskQuestionScreenState();
}

class _AskQuestionScreenState extends State<AskQuestionScreen> {
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
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('questions', {
        'subject': _subject.text.trim(),
        'body': _body.text.trim(),
        'allow_publish': _allowPublish,
      });
      if (!mounted) return;
      showMessage(
        context,
        '${envelope['message']} ${dataOf(envelope).str('reference')}',
      );
      context.pop(true);
    } on ApiException catch (error) {
      setState(() => _error = error);
      if (error.errors.isEmpty && mounted) {
        showMessage(context, errorText(context, error), error: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: MemberPage(
        children: [
          PageTitle(
            context.tr('Ask a question'),
            subtitle: context.tr(
              'A TRA expert will answer. We will tell you by SMS and in your notifications.',
            ),
            back: context.tr('Ask TRA'),
            onBack: () => context.pop(),
          ),
          const SizedBox(height: 24),
          WebCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                WebField(
                  label: context.tr('Your question in one line'),
                  controller: _subject,
                  error: _error?.field('subject'),
                ),
                WebField(
                  label: context.tr('Details'),
                  controller: _body,
                  maxLines: 8,
                  help: context.tr(
                    'Do not include your TIN, phone number or other private details.',
                  ),
                  error: _error?.field('body'),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  value: _allowPublish,
                  onChanged: (value) =>
                      setState(() => _allowPublish = value ?? false),
                  title: Text(
                    context.tr(
                      'TRA may publish my question and the answer without my name, to help other members',
                    ),
                    style: const TextStyle(fontSize: 15),
                  ),
                ),
                const SizedBox(height: 12),
                WebButton(
                  context.tr('Send to TRA'),
                  kind: BtnKind.brand,
                  onPressed: _send,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
