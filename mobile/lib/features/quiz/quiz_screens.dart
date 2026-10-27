import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class QuizzesScreen extends StatelessWidget {
  const QuizzesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Quizzes')),
        actions: [IconButton(tooltip: context.tr('Leaderboard'), onPressed: () => context.push('/leaderboard'), icon: const Icon(Icons.leaderboard_outlined))],
      ),
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('quizzes')),
        isEmpty: (quizzes) => quizzes.isEmpty,
        empty: EmptyPanel(message: context.tr('No quizzes are open right now.'), icon: Icons.quiz_outlined),
        builder: (context, quizzes, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: quizzes.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final quiz = quizzes[index];
              final result = quiz.obj('result');
              return Tile(
                onTap: () => context.push('/quiz/${quiz.str('quiz_id')}'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    if (quiz.strOrNull('category') != null) Pill(quiz.str('category')),
                    if (quiz.str('mode') == 'practice') ...[const SizedBox(width: 6), Pill.tone(context.tr('Practice'), 'info')],
                    const Spacer(),
                    if (result != null)
                      Pill.tone('${result.number('percent').round()}%', result.flag('passed') ? 'good' : 'warn'),
                  ]),
                  const SizedBox(height: 8),
                  Text(quiz.str('title'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 4),
                  Wrap(spacing: 14, children: [
                    if (quiz.intOrNull('questions_count') != null)
                      _Meta(Icons.help_outline, context.trPlural(':count question|:count questions', quiz.integer('questions_count'))),
                    if (quiz.intOrNull('time_limit_minutes') != null) _Meta(Icons.timer_outlined, context.tr(':count min', {'count': quiz.integer('time_limit_minutes')})),
                    if (quiz.date('closes_at') != null) _Meta(Icons.event_outlined, context.tr('Closes :date', {'date': context.date(quiz.date('closes_at'))})),
                  ]),
                ]),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta(this.icon, this.text);

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 16, color: Brand.slate),
        const SizedBox(width: 4),
        Text(text, style: const TextStyle(color: Brand.slate, fontSize: 13)),
      ]);
}

/// Rules, attempts left, start or resume.
class QuizScreen extends StatelessWidget {
  const QuizScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Quiz'))),
      body: Loader<Map<String, dynamic>>(
        load: () async => ((await context.api.get('quizzes/$id')) as Map).cast<String, dynamic>(),
        builder: (context, quiz, reload) {
          final resuming = quiz.strOrNull('attempt_in_progress') != null;
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(quiz.str('title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Paragraphs(quiz.strOrNull('description')),
                const SizedBox(height: 16),
                Card(
                  child: Column(children: [
                    if (quiz.intOrNull('questions_count') != null)
                      ListTile(leading: const Icon(Icons.help_outline), title: Text(context.trPlural(':count question|:count questions', quiz.integer('questions_count')))),
                    ListTile(
                      leading: const Icon(Icons.timer_outlined),
                      title: Text(quiz.intOrNull('time_limit_minutes') == null
                          ? context.tr('No time limit')
                          : context.tr(':count minutes to finish', {'count': quiz.integer('time_limit_minutes')})),
                    ),
                    if (quiz.str('mode') != 'practice')
                      ListTile(leading: const Icon(Icons.flag_outlined), title: Text(context.tr('Pass mark: :mark%', {'mark': quiz.integer('pass_mark')}))),
                    ListTile(
                      leading: const Icon(Icons.replay),
                      title: Text(quiz.intOrNull('attempts_left') == null
                          ? context.tr('Unlimited attempts')
                          : context.tr('Attempts left: :count', {'count': quiz.integer('attempts_left')})),
                    ),
                  ]),
                ),
                if (quiz.strOrNull('instructions') != null) ...[
                  const SizedBox(height: 16),
                  SectionTitle(context.tr('Instructions')),
                  Paragraphs(quiz.str('instructions')),
                ],
                const SizedBox(height: 24),
                if (quiz.flag('can_start'))
                  BusyButton(
                    label: context.tr(resuming ? 'Continue the quiz' : 'Start the quiz'),
                    icon: Icons.play_arrow,
                    onPressed: () async {
                      final envelope = await runAction(context, () => context.api.post('quizzes/$id/start'));
                      if (envelope != null && context.mounted) {
                        await context.push('/quiz/$id/attempt', extra: dataOf(envelope));
                        reload();
                      }
                    },
                  )
                else
                  Tile(color: Brand.yellowWash, child: Text(quiz.str('reason'))),
                const SizedBox(height: 12),
                BusyButton(label: context.tr('My past attempts'), outlined: true, onPressed: () async => context.push('/quiz/$id/attempts')),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Answering questions, one per page, with autosave and a timer.
class AttemptScreen extends StatefulWidget {
  const AttemptScreen({super.key, required this.quizId, required this.attempt});

  final String quizId;
  final Map<String, dynamic> attempt;

  @override
  State<AttemptScreen> createState() => _AttemptScreenState();
}

class _AttemptScreenState extends State<AttemptScreen> {
  late final List<Map<String, dynamic>> _questions = widget.attempt.list('questions');
  late final Map<String, Set<String>> _answers = {
    for (final question in _questions) question.str('question_id'): question.strings('selected_answer_ids').toSet(),
  };
  final _pages = PageController();
  int _index = 0;
  int? _secondsLeft;
  Timer? _timer;
  bool _submitting = false;
  final _saving = <String>{};

  String get _attemptId => widget.attempt.str('attempt_id');

  @override
  void initState() {
    super.initState();
    _secondsLeft = widget.attempt.intOrNull('seconds_left');
    if (_secondsLeft != null) {
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted) return;
        setState(() => _secondsLeft = (_secondsLeft ?? 0) - 1);
        if ((_secondsLeft ?? 1) <= 0) {
          _timer?.cancel();
          _submit(auto: true);
        }
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pages.dispose();
    super.dispose();
  }

  Future<void> _choose(Map<String, dynamic> question, String answerId) async {
    final id = question.str('question_id');
    final multiple = question.str('question_type') == 'multiple_select';
    setState(() {
      final chosen = _answers[id]!;
      if (multiple) {
        chosen.contains(answerId) ? chosen.remove(answerId) : chosen.add(answerId);
      } else {
        chosen
          ..clear()
          ..add(answerId);
      }
      _saving.add(id);
    });

    try {
      final envelope = await context.api.put('quizzes/attempts/$_attemptId/answers', {'question_id': id, 'answer_ids': _answers[id]!.toList()});
      final seconds = dataOf(envelope).intOrNull('seconds_left');
      if (seconds != null && mounted) setState(() => _secondsLeft = seconds);
    } on ApiException catch (error) {
      if (!mounted) return;
      showMessage(context, errorText(context, error), error: true);
      if (error.statusCode == 409) _submit(auto: true);
    } finally {
      if (mounted) setState(() => _saving.remove(id));
    }
  }

  Future<void> _submit({bool auto = false}) async {
    if (_submitting) return;
    if (!auto) {
      final unanswered = _answers.values.where((answer) => answer.isEmpty).length;
      final message = unanswered > 0
          ? context.trPlural('You have :count unanswered question. Submit anyway?|You have :count unanswered questions. Submit anyway?', unanswered)
          : context.tr('Submit your answers? You cannot change them afterwards.');
      if (!await confirm(context, message, action: context.tr('Submit'))) return;
    }
    if (!mounted) return;
    setState(() => _submitting = true);
    _timer?.cancel();

    try {
      final envelope = await context.api.post('quizzes/attempts/$_attemptId/submit');
      if (mounted) context.pushReplacement('/quiz/${widget.quizId}/result', extra: dataOf(envelope));
    } catch (error) {
      if (mounted) {
        setState(() => _submitting = false);
        showMessage(context, errorText(context, error), error: true);
      }
    }
  }

  String _clock(int seconds) {
    final s = seconds < 0 ? 0 : seconds;
    return '${(s ~/ 60).toString().padLeft(2, '0')}:${(s % 60).toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final answered = _answers.values.where((answer) => answer.isNotEmpty).length;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await confirm(context, context.tr('Leave the quiz? Your answers are saved and you can continue later while time remains.'), action: context.tr('Leave'))) {
          if (context.mounted) Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr('Question :number of :total', {'number': _index + 1, 'total': _questions.length})),
          actions: [
            if (_secondsLeft != null)
              Padding(
                padding: const EdgeInsets.only(right: 12),
                child: Center(
                  child: Pill(
                    _clock(_secondsLeft!),
                    color: (_secondsLeft ?? 0) < 60 ? Brand.danger : Brand.ink,
                    filled: true,
                  ),
                ),
              ),
          ],
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(4),
            child: LinearProgressIndicator(value: _questions.isEmpty ? 0 : answered / _questions.length, backgroundColor: Brand.yellowWash, color: Brand.ink),
          ),
        ),
        body: _questions.isEmpty
            ? EmptyPanel(message: context.tr('This quiz has no questions yet.'))
            : PageView.builder(
                controller: _pages,
                itemCount: _questions.length,
                onPageChanged: (index) => setState(() => _index = index),
                itemBuilder: (context, index) {
                  final question = _questions[index];
                  final id = question.str('question_id');
                  final multiple = question.str('question_type') == 'multiple_select';
                  return ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(question.str('question_text'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, height: 1.35)),
                      const SizedBox(height: 6),
                      Text(context.tr(multiple ? 'Choose all that apply.' : 'Choose one answer.'), style: const TextStyle(color: Brand.slate)),
                      const SizedBox(height: 16),
                      for (final answer in question.list('answers'))
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _AnswerOption(
                            text: answer.str('answer_text'),
                            selected: _answers[id]!.contains(answer.str('answer_id')),
                            multiple: multiple,
                            onTap: _submitting ? null : () => _choose(question, answer.str('answer_id')),
                          ),
                        ),
                      if (_saving.contains(id)) Text(context.tr('Saving…'), style: const TextStyle(color: Brand.slate, fontSize: 12)),
                    ],
                  );
                },
              ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(children: [
              if (_index > 0)
                Expanded(child: OutlinedButton(onPressed: () => _pages.previousPage(duration: const Duration(milliseconds: 250), curve: Curves.easeOut), child: Text(context.tr('Back'))))
              else
                const Spacer(),
              const SizedBox(width: 12),
              Expanded(
                child: _index < _questions.length - 1
                    ? FilledButton(onPressed: () => _pages.nextPage(duration: const Duration(milliseconds: 250), curve: Curves.easeOut), child: Text(context.tr('Next')))
                    : FilledButton(
                        style: FilledButton.styleFrom(backgroundColor: Brand.success),
                        onPressed: _submitting ? null : () => _submit(),
                        child: _submitting ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text(context.tr('Submit')),
                      ),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}

class _AnswerOption extends StatelessWidget {
  const _AnswerOption({required this.text, required this.selected, required this.multiple, this.onTap});

  final String text;
  final bool selected;
  final bool multiple;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? Brand.yellow : Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: selected ? Brand.ink : Brand.line, width: selected ? 2 : 1)),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            Icon(multiple
                ? (selected ? Icons.check_box : Icons.check_box_outline_blank)
                : (selected ? Icons.radio_button_checked : Icons.radio_button_unchecked)),
            const SizedBox(width: 12),
            Expanded(child: Text(text, style: const TextStyle(fontSize: 16))),
          ]),
        ),
      ),
    );
  }
}

/// Score, points and (when allowed) the correct answers.
class ResultScreen extends StatelessWidget {
  const ResultScreen({super.key, required this.quizId, this.result, this.attemptId});

  final String quizId;
  final Map<String, dynamic>? result;
  final String? attemptId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Your result'))),
      body: Loader<Map<String, dynamic>>(
        load: () async => result ?? ((await context.api.get('quizzes/$quizId/attempt/$attemptId/results')) as Map).cast<String, dynamic>(),
        builder: (context, data, _) {
          final passed = data.flag('passed');
          final questions = data.list('questions');
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Tile(
                color: passed ? const Color(0xFFDCFCE7) : Brand.yellowWash,
                child: Column(children: [
                  Icon(passed ? Icons.emoji_events : Icons.school, size: 56, color: passed ? Brand.success : Brand.ink),
                  const SizedBox(height: 8),
                  Text('${data.number('percent').round()}%', style: Theme.of(context).textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w800)),
                  Text(context.tr(':score of :max marks', {'score': data.number('score').round(), 'max': data.number('max_score').round()})),
                  const SizedBox(height: 8),
                  Text(context.tr(passed ? 'Well done, you passed!' : 'Keep learning and try again.'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  if (data.integer('points_awarded') > 0) ...[
                    const SizedBox(height: 8),
                    Pill(context.tr('+:points points', {'points': data.integer('points_awarded')}), color: Brand.success, filled: true),
                  ],
                  if (data.flag('auto_submitted')) ...[
                    const SizedBox(height: 8),
                    Text(context.tr('Time ran out, so your answers were submitted for you.'), textAlign: TextAlign.center),
                  ],
                ]),
              ),
              const SizedBox(height: 16),
              if (!data.flag('answers_visible'))
                Text(context.tr('The correct answers will be shown after the quiz closes.'), style: const TextStyle(color: Brand.slate))
              else ...[
                SectionTitle(context.tr('Review your answers')),
                for (final (index, question) in questions.indexed)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Tile(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Icon(question.flag('is_correct') ? Icons.check_circle : Icons.cancel, color: question.flag('is_correct') ? Brand.success : Brand.danger),
                          const SizedBox(width: 8),
                          Expanded(child: Text('${index + 1}. ${question.str('question_text')}', style: const TextStyle(fontWeight: FontWeight.w600))),
                        ]),
                        if (question.strOrNull('explanation') != null) ...[
                          const SizedBox(height: 8),
                          Text(question.str('explanation'), style: const TextStyle(color: Brand.charcoal)),
                        ],
                      ]),
                    ),
                  ),
              ],
              const SizedBox(height: 16),
              FilledButton(onPressed: () => context.pop(), child: Text(context.tr('Done'))),
              const SizedBox(height: 8),
              OutlinedButton(onPressed: () => context.push('/leaderboard'), child: Text(context.tr('See the leaderboard'))),
            ],
          );
        },
      ),
    );
  }
}

class AttemptsScreen extends StatelessWidget {
  const AttemptsScreen({super.key, required this.quizId});

  final String quizId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My past attempts'))),
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('quizzes/$quizId/my-attempts')),
        isEmpty: (rows) => rows.isEmpty,
        empty: EmptyPanel(message: context.tr('You have not finished this quiz yet.'), icon: Icons.history),
        builder: (context, rows, _) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Column(children: [
                for (final row in rows)
                  ListTile(
                    title: Text(context.tr('Attempt :number', {'number': row.integer('attempt_number')})),
                    subtitle: Text(context.dateTime(row.date('submitted_at'))),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      if (row.flag('is_best_attempt')) const Padding(padding: EdgeInsets.only(right: 8), child: Icon(Icons.star, color: Color(0xFFCA8A04))),
                      Text('${row.number('percent').round()}%', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                    ]),
                    onTap: () => context.push('/quiz/$quizId/result/${row.str('attempt_id')}'),
                  ),
              ]),
            ),
          ],
        ),
      ),
    );
  }
}

class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({super.key});

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> {
  String _period = 'all';
  String _scope = 'national';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Leaderboard'))),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Column(children: [
            SegmentedButton<String>(
              showSelectedIcon: false,
              segments: [
                ButtonSegment(value: 'national', label: Text(context.tr('Tanzania'))),
                ButtonSegment(value: 'region', label: Text(context.tr('My region'))),
                ButtonSegment(value: 'institution', label: Text(context.tr('My school'))),
              ],
              selected: {_scope},
              onSelectionChanged: (value) => setState(() => _scope = value.first),
            ),
            const SizedBox(height: 8),
            Wrap(spacing: 8, children: [
              for (final option in {'all': 'All time', 'month': 'This month', 'week': 'This week'}.entries)
                ChoiceChip(label: Text(context.tr(option.value)), selected: _period == option.key, onSelected: (_) => setState(() => _period = option.key)),
            ]),
          ]),
        ),
        Expanded(
          child: Loader<Map<String, dynamic>>(
            key: ValueKey('$_scope-$_period'),
            load: () async => ((await context.api.get('leaderboard', query: {'period': _period, 'scope': _scope})) as Map).cast<String, dynamic>(),
            builder: (context, data, reload) {
              if (data.containsKey('enabled') && !data.flag('enabled')) {
                return EmptyPanel(message: context.tr('The leaderboard is turned off for now.'), icon: Icons.leaderboard_outlined);
              }
              final entries = data.list('leaderboard');
              final me = data.obj('me');
              return RefreshIndicator(
                onRefresh: reload,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (me != null && !entries.any((entry) => entry.flag('is_me')))
                      Padding(padding: const EdgeInsets.only(bottom: 12), child: _Row(entry: me)),
                    if (entries.isEmpty) EmptyPanel(message: context.tr('No points yet. Complete lessons and quizzes to get on the board.')),
                    Card(child: Column(children: [for (final entry in entries) _Row(entry: entry)])),
                  ],
                ),
              );
            },
          ),
        ),
      ]),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final rank = entry.integer('rank');
    final medal = switch (rank) { 1 => const Color(0xFFCA8A04), 2 => const Color(0xFF9CA3AF), 3 => const Color(0xFFB45309), _ => null };
    return ListTile(
      tileColor: entry.flag('is_me') ? Brand.yellowWash : null,
      leading: CircleAvatar(
        backgroundColor: medal ?? Brand.mist,
        foregroundColor: medal != null ? Colors.white : Brand.ink,
        child: Text('$rank', style: const TextStyle(fontWeight: FontWeight.w700)),
      ),
      title: Text(entry.flag('is_me') ? context.tr(':name (you)', {'name': entry.str('display_name')}) : entry.str('display_name'), style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: entry.strOrNull('place') == null ? null : Text(entry.str('place')),
      trailing: Text(context.tr(':points pts', {'points': context.number(entry.integer('points'))}), style: const TextStyle(fontWeight: FontWeight.w700)),
    );
  }
}
