import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';
import '../auth/auth_screens.dart';

String _statusLabel(BuildContext context, String status) =>
    context.tr(switch (status) {
      'happening' => 'Happening now',
      'ended' => 'Finished',
      'results' => 'Results',
      'cancelled' => 'Cancelled',
      _ => 'Open',
    });

/// "Competitions" (livewire/member/competitions/index.blade.php).
class CompetitionsScreen extends StatelessWidget {
  const CompetitionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('competitions')),
        builder: (context, rows, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              context.tr('Competitions'),
              subtitle: context.tr(
                'Show what you know, win prizes and earn certificates.',
              ),
            ),
            const SizedBox(height: 24),
            if (rows.isEmpty)
              MistBox(
                context.tr(
                  'No competitions are open right now. Check back soon.',
                ),
                color: Colors.white,
              ),
            for (final competition in rows)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: WebCard(
                  highlight: competition.obj('my_entry') != null,
                  onTap: () =>
                      context.push('/competitions/${competition.str('slug')}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          Tag(
                            context.tr(
                              competition.str('type') == 'quiz'
                                  ? 'Quiz challenge'
                                  : 'Submission',
                            ),
                          ),
                          Tag(
                            _statusLabel(context, competition.str('status')),
                            dark: competition.str('status') == 'happening',
                            color: competition.str('status') == 'happening'
                                ? null
                                : Brand.mist,
                          ),
                          if (competition.obj('my_entry') != null)
                            Tag(
                              context.tr('Registered'),
                              color: const Color(0xFFDCFCE7),
                            ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Text(
                        competition.str('title'),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          height: 1.3,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '${context.date(competition.date('starts_at'))} – ${context.date(competition.date('ends_at'))}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: Brand.slate,
                        ),
                      ),
                      if (competition.obj('my_entry')?.intOrNull('rank') !=
                          null) ...[
                        const SizedBox(height: 8),
                        Text(
                          context.tr('You placed :rank', {
                            'rank': competition
                                .obj('my_entry')!
                                .integer('rank'),
                          }),
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                      ],
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

/// One competition (livewire/member/competitions/show.blade.php).
class CompetitionScreen extends StatefulWidget {
  const CompetitionScreen({super.key, required this.slug});

  final String slug;

  @override
  State<CompetitionScreen> createState() => _CompetitionScreenState();
}

class _CompetitionScreenState extends State<CompetitionScreen> {
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();
  final _text = TextEditingController();
  final _url = TextEditingController();
  PlatformFile? _file;

  @override
  void dispose() {
    _text.dispose();
    _url.dispose();
    super.dispose();
  }

  Future<void> _register() async {
    final result = await runAction(
      context,
      () => context.api.post('competitions/${widget.slug}/register'),
      showServerMessage: true,
    );
    if (result != null) _loader.currentState?.reload();
  }

  Future<void> _pick() async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
    );
    if (file != null) setState(() => _file = file);
  }

  Future<void> _submit() async {
    final bytes = await _file?.readAsBytes();
    if (!mounted) return;
    final form = FormData.fromMap({
      if (_text.text.trim().isNotEmpty) 'text': _text.text.trim(),
      if (_url.text.trim().isNotEmpty) 'url': _url.text.trim(),
      if (bytes != null)
        'file': MultipartFile.fromBytes(bytes, filename: _file!.name),
    });
    final result = await runAction(
      context,
      () => context.api.post('competitions/${widget.slug}/entry', form),
      showServerMessage: true,
    );
    if (result != null) _loader.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: () async =>
            ((await context.api.get('competitions/${widget.slug}')) as Map)
                .cast<String, dynamic>(),
        builder: (context, competition, reload) {
          final entry = competition.obj('my_entry');
          final status = competition.str('status');
          final results = competition.list('results');
          final closes = competition.date('registration_closes_at');
          final registrationOpen =
              (status == 'published' || status == 'happening') &&
              (closes == null || closes.isAfter(DateTime.now()));
          final isQuiz = competition.str('type') == 'quiz';
          final kind = competition.str('submission_kind', 'text');

          return MemberPage(
            onRefresh: reload,
            children: [
              LinkText(
                context.tr('Competitions'),
                onTap: () => context.go('/competitions'),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                children: [
                  Tag(context.tr(isQuiz ? 'Quiz challenge' : 'Submission')),
                  Tag(
                    _statusLabel(context, status),
                    dark: status == 'happening',
                    color: status == 'happening' ? null : Colors.white,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                competition.str('title'),
                style: const TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                ),
              ),
              if (status == 'cancelled') ...[
                const SizedBox(height: 14),
                MistBox(
                  context.tr('This competition was cancelled.'),
                  color: const Color(0xFFFEE4E2),
                ),
              ],
              const SizedBox(height: 20),
              FactBox(
                label: context.tr('When'),
                value:
                    '${context.dateTime(competition.date('starts_at'))} – ${context.dateTime(competition.date('ends_at'))}',
              ),
              const SizedBox(height: 20),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: entry != null ? Brand.yellowWash : Colors.white,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: entry != null ? Brand.ink : Brand.line,
                    width: 2,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      context.tr(entry != null ? 'Your entry' : 'Take part'),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Brand.slate,
                      ),
                    ),
                    const SizedBox(height: 6),
                    if (entry == null) ...[
                      if (closes != null)
                        Text(
                          context.tr('Registration closes :date', {
                            'date': context.dateTime(closes),
                          }),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      const SizedBox(height: 12),
                      if (registrationOpen)
                        WebButton(
                          context.tr('Register'),
                          kind: BtnKind.brand,
                          onPressed: _register,
                        ),
                    ] else if (entry.intOrNull('rank') != null) ...[
                      Text(
                        context.tr('You placed :rank', {
                          'rank': entry.integer('rank'),
                        }),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ] else ...[
                      Text(
                        context.tr(
                          entry.str('status') == 'submitted'
                              ? 'Received'
                              : (entry.str('status') == 'withdrawn'
                                    ? 'Withdrawn'
                                    : 'You are registered'),
                        ),
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (isQuiz) ...[
                        if (status == 'happening' &&
                            competition.strOrNull('quiz_id') != null) ...[
                          Text(
                            context.tr(
                              'Your best attempt before the end counts.',
                            ),
                          ),
                          const SizedBox(height: 12),
                          WebButton(
                            context.tr('Take the quiz'),
                            kind: BtnKind.brand,
                            onPressed: () async => context.push(
                              '/quiz/${competition.str('quiz_id')}',
                            ),
                          ),
                        ] else
                          Text(
                            context.tr(
                              'The quiz opens when the competition starts.',
                            ),
                          ),
                      ] else if (status == 'happening' &&
                          entry.str('status') != 'disqualified') ...[
                        if (kind == 'text')
                          WebField(
                            label: context.tr('Your entry'),
                            controller: _text,
                            maxLines: 10,
                          ),
                        if (kind == 'link')
                          WebField(
                            label: context.tr('Link to your entry'),
                            controller: _url,
                            keyboard: TextInputType.url,
                            hint: 'https://',
                          ),
                        if (kind == 'file') ...[
                          if (entry.str('status') == 'submitted')
                            Text(
                              context.tr(
                                'A file is already submitted. Uploading a new one replaces it.',
                              ),
                              style: const TextStyle(color: Brand.slate),
                            ),
                          const SizedBox(height: 8),
                          WebButton(
                            _file?.name ?? context.tr('Your file'),
                            kind: BtnKind.ghost,
                            icon: Icons.attach_file,
                            onPressed: _pick,
                          ),
                          const SizedBox(height: 12),
                        ],
                        WebButton(
                          context.tr('Submit entry'),
                          onPressed: _submit,
                        ),
                      ],
                    ],
                  ],
                ),
              ),
              if (competition.strOrNull('description') != null) ...[
                const SizedBox(height: 28),
                Paragraphs(competition.str('description')),
              ],
              if (competition.strOrNull('rules') != null) ...[
                const SizedBox(height: 24),
                SectionHeading(context.tr('Rules'), size: 22),
                const SizedBox(height: 8),
                Paragraphs(competition.str('rules')),
              ],
              const SizedBox(height: 28),
              SectionHeading(context.tr('Results'), size: 22),
              const SizedBox(height: 12),
              if (results.isEmpty)
                MistBox(context.tr('Results coming soon'), color: Colors.white)
              else
                WebCard(
                  padding: EdgeInsets.zero,
                  child: Column(
                    children: [
                      for (final row in results)
                        ListTile(
                          leading: CircleAvatar(
                            backgroundColor: Brand.ink,
                            foregroundColor: Brand.yellow,
                            child: Text(
                              '${row.integer('rank')}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          title: Text(
                            row.str('name'),
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          subtitle: row.strOrNull('institution') == null
                              ? null
                              : Text(row.str('institution')),
                          trailing: Text(
                            row.number('score').toStringAsFixed(1),
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                        ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
