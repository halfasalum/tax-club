import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class CompetitionsScreen extends StatelessWidget {
  const CompetitionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Competitions'))),
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('competitions')),
        isEmpty: (rows) => rows.isEmpty,
        empty: EmptyPanel(message: context.tr('No competitions right now. Check back soon.'), icon: Icons.emoji_events_outlined),
        builder: (context, rows, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final competition = rows[index];
              final entry = competition.obj('my_entry');
              return Tile(
                onTap: () => context.push('/competitions/${competition.str('slug')}'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Wrap(spacing: 6, children: [
                    Pill(context.tr(competition.str('type') == 'quiz' ? 'Quiz challenge' : 'Submission')),
                    Pill.tone(labelOf(context, 'event_status', competition.str('status')), toneOf(competition.str('status'))),
                    if (entry != null) Pill.tone(context.tr('Registered'), 'good'),
                    if (entry?.intOrNull('rank') != null) Pill(context.tr('Position :rank', {'rank': entry!.integer('rank')}), color: Brand.success, filled: true),
                  ]),
                  const SizedBox(height: 8),
                  Text(competition.str('title'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  InfoRow(icon: Icons.schedule, text: '${context.date(competition.date('starts_at'))} – ${context.date(competition.date('ends_at'))}'),
                ]),
              );
            },
          ),
        ),
      ),
    );
  }
}

class CompetitionScreen extends StatefulWidget {
  const CompetitionScreen({super.key, required this.slug});

  final String slug;

  @override
  State<CompetitionScreen> createState() => _CompetitionScreenState();
}

class _CompetitionScreenState extends State<CompetitionScreen> {
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();

  Future<void> _register() async {
    final result = await runAction(context, () => context.api.post('competitions/${widget.slug}/register'), showServerMessage: true);
    if (result != null) _loader.currentState?.reload();
  }

  Future<void> _submit(String kind) async {
    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _EntrySheet(slug: widget.slug, kind: kind),
    );
    if (sent == true) _loader.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Competition'))),
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: () async => ((await context.api.get('competitions/${widget.slug}')) as Map).cast<String, dynamic>(),
        builder: (context, competition, reload) {
          final entry = competition.obj('my_entry');
          final status = competition.str('status');
          final open = status == 'published' || status == 'happening';
          final results = competition.list('results');
          final closes = competition.date('registration_closes_at');
          final registrationOpen = open && (closes == null || closes.isAfter(DateTime.now()));

          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Wrap(spacing: 6, children: [
                  Pill(context.tr(competition.str('type') == 'quiz' ? 'Quiz challenge' : 'Submission')),
                  Pill.tone(labelOf(context, 'event_status', status), toneOf(status)),
                ]),
                const SizedBox(height: 10),
                Text(competition.str('title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 6),
                InfoRow(icon: Icons.schedule, text: '${context.dateTime(competition.date('starts_at'))} – ${context.dateTime(competition.date('ends_at'))}'),
                if (closes != null) InfoRow(icon: Icons.how_to_reg_outlined, text: context.tr('Registration closes :date', {'date': context.dateTime(closes)})),
                const SizedBox(height: 16),
                if (entry == null && registrationOpen)
                  BusyButton(label: context.tr('Register'), icon: Icons.how_to_reg, onPressed: _register)
                else if (entry != null) ...[
                  Tile(
                    color: Brand.yellowWash,
                    child: Text(entry.intOrNull('rank') != null
                        ? context.tr('You finished in position :rank.', {'rank': entry.integer('rank')})
                        : context.tr(switch (entry.str('status')) {
                            'submitted' => 'Your entry has been received.',
                            'disqualified' => 'Your entry was disqualified.',
                            _ => 'You are registered.',
                          })),
                  ),
                  const SizedBox(height: 10),
                  if (status == 'happening' && competition.str('type') == 'quiz' && competition.strOrNull('quiz_id') != null)
                    BusyButton(label: context.tr('Take the quiz'), icon: Icons.quiz_outlined, onPressed: () async => context.push('/quiz/${competition.str('quiz_id')}')),
                  if (status == 'happening' && competition.str('type') != 'quiz' && entry.str('status') != 'disqualified')
                    BusyButton(
                      label: context.tr(entry.str('status') == 'submitted' ? 'Replace my entry' : 'Submit my entry'),
                      icon: Icons.upload_outlined,
                      onPressed: () => _submit(competition.str('submission_kind', 'text')),
                    ),
                ],
                if (competition.strOrNull('description') != null) ...[
                  const SizedBox(height: 20),
                  SectionTitle(context.tr('About')),
                  Paragraphs(competition.str('description')),
                ],
                if (competition.strOrNull('rules') != null) ...[
                  const SizedBox(height: 12),
                  SectionTitle(context.tr('Rules')),
                  Paragraphs(competition.str('rules')),
                ],
                if (results.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  SectionTitle(context.tr('Results')),
                  Card(
                    child: Column(children: [
                      for (final row in results)
                        ListTile(
                          leading: CircleAvatar(backgroundColor: Brand.yellow, foregroundColor: Brand.ink, child: Text('${row.integer('rank')}')),
                          title: Text(row.str('name')),
                          subtitle: row.strOrNull('institution') == null ? null : Text(row.str('institution')),
                          trailing: Text(row.number('score').toStringAsFixed(1), style: const TextStyle(fontWeight: FontWeight.w700)),
                        ),
                    ]),
                  ),
                ],
                const SizedBox(height: 24),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _EntrySheet extends StatefulWidget {
  const _EntrySheet({required this.slug, required this.kind});

  final String slug;
  final String kind;

  @override
  State<_EntrySheet> createState() => _EntrySheetState();
}

class _EntrySheetState extends State<_EntrySheet> {
  final _text = TextEditingController();
  final _url = TextEditingController();
  PlatformFile? _file;

  @override
  void dispose() {
    _text.dispose();
    _url.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    final file = await FilePicker.pickFile(type: FileType.custom, allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png']);
    if (file != null) setState(() => _file = file);
  }

  Future<void> _send() async {
    final bytes = await _file?.readAsBytes();
    if (!mounted) return;
    final form = FormData.fromMap({
      if (_text.text.trim().isNotEmpty) 'text': _text.text.trim(),
      if (_url.text.trim().isNotEmpty) 'url': _url.text.trim(),
      if (bytes != null) 'file': MultipartFile.fromBytes(bytes, filename: _file!.name),
    });
    final result = await runAction(context, () => context.api.post('competitions/${widget.slug}/entry', form), showServerMessage: true);
    if (result != null && mounted) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(context.tr('Submit my entry'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          if (widget.kind == 'text' || widget.kind == 'essay')
            TextField(controller: _text, minLines: 6, maxLines: 14, maxLength: 20000, decoration: InputDecoration(labelText: context.tr('Your entry'), alignLabelWithHint: true)),
          if (widget.kind == 'link' || widget.kind == 'url')
            TextField(controller: _url, keyboardType: TextInputType.url, decoration: InputDecoration(labelText: context.tr('Link to your work'), hintText: 'https://')),
          if (widget.kind == 'file') ...[
            OutlinedButton.icon(onPressed: _pick, icon: const Icon(Icons.attach_file), label: Text(_file?.name ?? context.tr('Choose a PDF or photo (max 10 MB)'))),
          ],
          const SizedBox(height: 16),
          BusyButton(label: context.tr('Send'), icon: Icons.send, onPressed: _send),
        ]),
      ),
    );
  }
}
