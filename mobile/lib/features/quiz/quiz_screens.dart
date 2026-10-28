import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';
import '../engage/achievements_screen.dart';

/// "Quizzes" (livewire/member/quizzes/index.blade.php).
class QuizzesScreen extends StatelessWidget {
  const QuizzesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('quizzes')),
        builder: (context, quizzes, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              context.tr('Quizzes'),
              subtitle: context.tr(
                'Test what you have learned and earn points.',
              ),
            ),
            const SizedBox(height: 24),
            if (quizzes.isEmpty)
              MistBox(
                context.tr('No quizzes are open right now. Check back soon.'),
                color: Colors.white,
              ),
            for (final quiz in quizzes)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: WebCard(
                  highlight: quiz.obj('result') != null,
                  onTap: () => context.push('/quiz/${quiz.str('quiz_id')}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Wrap(
                        spacing: 10,
                        runSpacing: 6,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Tag(
                            context.tr(
                              quiz.str('mode') == 'practice'
                                  ? 'Practice'
                                  : 'Earns points',
                            ),
                          ),
                          if (quiz.intOrNull('time_limit_minutes') != null)
                            Text(
                              context.tr(':minutes min', {
                                'minutes': quiz.integer('time_limit_minutes'),
                              }),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 14,
                              ),
                            ),
                          if (quiz.date('closes_at') != null)
                            Text(
                              context.tr('closes :date', {
                                'date': context.date(quiz.date('closes_at')),
                              }),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                color: Brand.slate,
                                fontSize: 14,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Text(
                        quiz.str('title'),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          height: 1.3,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        quiz.obj('result') != null
                            ? context.tr('Best so far: :percent%', {
                                'percent': quiz
                                    .obj('result')!
                                    .number('percent')
                                    .round(),
                              })
                            : context.trPlural(
                                ':count question|:count questions',
                                quiz.integer('questions_count'),
                              ),
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                          color: quiz.obj('result') != null
                              ? Brand.ink
                              : Brand.slate,
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

/// "Before you start" (livewire/member/quizzes/show.blade.php).
class QuizScreen extends StatelessWidget {
  const QuizScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () async {
          final api = context.api;
          final results = await Future.wait([
            api.get('quizzes/$id'),
            api.get('quizzes/$id/my-attempts').catchError((_) => <dynamic>[]),
          ]);
          return {
            ...(results[0] as Map).cast<String, dynamic>(),
            'attempts': results[1],
          };
        },
        builder: (context, quiz, reload) {
          final resuming = quiz.strOrNull('attempt_in_progress') != null;
          final attempts = quiz.list('attempts');
          final minutes = quiz.intOrNull('time_limit_minutes');
          final practice = quiz.str('mode') == 'practice';

          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                quiz.str('title'),
                subtitle: quiz.strOrNull('description'),
                back: context.tr('Quizzes'),
                onBack: () => context.go('/quizzes'),
              ),
              const SizedBox(height: 24),
              WebCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('Before you start'),
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Bullets([
                      context.trPlural(
                        ':count question|:count questions',
                        quiz.integer('questions_count'),
                      ),
                      if (minutes == null)
                        context.tr('No time limit.')
                      else
                        context.tr(
                          'You have :minutes minutes once you start. The quiz is submitted automatically when time runs out.',
                          {'minutes': minutes},
                        ),
                      if (practice)
                        context.tr('Practice quiz: no points, just learning.')
                      else
                        context.tr('Pass mark: :percent%', {
                          'percent': quiz.integer('pass_mark'),
                        }),
                      if (quiz.intOrNull('attempts_left') == null)
                        context.tr('You can try as many times as you like.')
                      else
                        context.trPlural(
                          '{0} No attempts left.|{1} :count attempt left.|[2,*] :count attempts left.',
                          quiz.integer('attempts_left'),
                        ),
                      if (!practice)
                        context.tr(
                          quiz.str('retained_result') == 'latest'
                              ? 'Your latest attempt counts.'
                              : 'Your best attempt counts.',
                        ),
                    ]),
                    if (quiz.strOrNull('instructions') != null) ...[
                      const SizedBox(height: 16),
                      Paragraphs(quiz.str('instructions')),
                    ],
                    const SizedBox(height: 22),
                    if (quiz.flag('can_start'))
                      WebButton(
                        context.tr(
                          resuming ? 'Continue your attempt' : 'Start quiz',
                        ),
                        kind: BtnKind.brand,
                        onPressed: () async {
                          final envelope = await runAction(
                            context,
                            () => context.api.post('quizzes/$id/start'),
                          );
                          if (envelope != null && context.mounted) {
                            await context.push(
                              '/quiz/$id/attempt',
                              extra: {
                                ...dataOf(envelope),
                                'title': quiz.str('title'),
                              },
                            );
                            reload();
                          }
                        },
                      )
                    else
                      MistBox(quiz.str('reason')),
                  ],
                ),
              ),
              if (attempts.isNotEmpty) ...[
                const SizedBox(height: 32),
                SectionHeading(context.tr('Your attempts')),
                const SizedBox(height: 14),
                for (final attempt in attempts)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: WebCard(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 16,
                      ),
                      onTap: () => context.push(
                        '/quiz/$id/result/${attempt.str('attempt_id')}',
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  context.tr('Attempt :number', {
                                    'number': attempt.integer('attempt_number'),
                                  }),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                Text(
                                  context.dateTime(
                                    attempt.date('submitted_at'),
                                  ),
                                  style: const TextStyle(
                                    color: Brand.slate,
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (attempt.flag('is_best_attempt')) ...[
                            Tag(context.tr('Counts')),
                            const SizedBox(width: 10),
                          ],
                          LinkText(
                            context.tr(':percent% · see result', {
                              'percent': attempt.number('percent').round(),
                            }),
                            onTap: () => context.push(
                              '/quiz/$id/result/${attempt.str('attempt_id')}',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// Taking a quiz (livewire/member/quizzes/take.blade.php), one question
/// per page, saved as you go, with a timer.
class AttemptScreen extends StatefulWidget {
  const AttemptScreen({super.key, required this.quizId, required this.attempt});

  final String quizId;
  final Map<String, dynamic> attempt;

  @override
  State<AttemptScreen> createState() => _AttemptScreenState();
}

class _AttemptScreenState extends State<AttemptScreen> {
  late final List<Map<String, dynamic>> _questions = widget.attempt.list(
    'questions',
  );
  late final Map<String, Set<String>> _answers = {
    for (final question in _questions)
      question.str('question_id'): question
          .strings('selected_answer_ids')
          .toSet(),
  };
  final _pages = PageController();
  int _index = 0;
  int? _secondsLeft;
  Timer? _timer;
  bool _submitting = false;

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
        chosen.contains(answerId)
            ? chosen.remove(answerId)
            : chosen.add(answerId);
      } else {
        chosen
          ..clear()
          ..add(answerId);
      }
    });

    try {
      final envelope = await context.api.put(
        'quizzes/attempts/$_attemptId/answers',
        {'question_id': id, 'answer_ids': _answers[id]!.toList()},
      );
      final seconds = dataOf(envelope).intOrNull('seconds_left');
      if (seconds != null && mounted) setState(() => _secondsLeft = seconds);
    } on ApiException catch (error) {
      if (!mounted) return;
      showMessage(context, errorText(context, error), error: true);
      if (error.statusCode == 409) _submit(auto: true);
    }
  }

  Future<void> _submit({bool auto = false}) async {
    if (_submitting) return;
    if (!auto && _answers.values.any((answer) => answer.isEmpty)) {
      if (!await confirm(
        context,
        context.tr('Some questions are not answered. Submit anyway?'),
        action: context.tr('Submit answers'),
      )) {
        return;
      }
    }
    if (!mounted) return;
    setState(() => _submitting = true);
    _timer?.cancel();

    try {
      final envelope = await context.api.post(
        'quizzes/attempts/$_attemptId/submit',
      );
      if (mounted) {
        context.pushReplacement(
          '/quiz/${widget.quizId}/result',
          extra: dataOf(envelope),
        );
      }
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
    final answered = _answers.values
        .where((answer) => answer.isNotEmpty)
        .length;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await confirm(
          context,
          context.tr(
            'Your answers are saved as you go. You can leave and come back before time runs out.',
          ),
          action: context.tr('Leave'),
        )) {
          if (context.mounted) Navigator.of(context).pop();
        }
      },
      child: MemberScaffold(
        body: Column(
          children: [
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          widget.attempt.str('title'),
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (_secondsLeft != null)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: (_secondsLeft ?? 0) < 60
                                ? Brand.danger
                                : Brand.ink,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            _clock(_secondsLeft!),
                            style: const TextStyle(
                              fontFamily: 'IBMPlexMono',
                              color: Colors.white,
                              fontWeight: FontWeight.w600,
                              fontSize: 18,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    context.tr(':answered of :total answered', {
                      'answered': answered,
                      'total': _questions.length,
                    }),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: Brand.slate,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: LinearProgressIndicator(
                      value: _questions.isEmpty
                          ? 0
                          : answered / _questions.length,
                      minHeight: 8,
                      backgroundColor: Brand.mist,
                      color: Brand.ink,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pages,
                itemCount: _questions.length,
                onPageChanged: (index) => setState(() => _index = index),
                itemBuilder: (context, index) {
                  final question = _questions[index];
                  final id = question.str('question_id');
                  final multiple =
                      question.str('question_type') == 'multiple_select';
                  return ListView(
                    padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
                    children: [
                      Text(
                        '${context.tr('Question :number of :total', {'number': index + 1, 'total': _questions.length})} · ${context.trPlural(':count mark|:count marks', question.integer('points', 1))}',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: Brand.slate,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        question.str('question_text'),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          height: 1.3,
                        ),
                      ),
                      if (multiple) ...[
                        const SizedBox(height: 6),
                        Text(
                          context.tr('Choose all that apply.'),
                          style: const TextStyle(
                            color: Brand.slate,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                      const SizedBox(height: 20),
                      for (final answer in question.list('answers'))
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _AnswerOption(
                            text: answer.str('answer_text'),
                            selected: _answers[id]!.contains(
                              answer.str('answer_id'),
                            ),
                            multiple: multiple,
                            onTap: _submitting
                                ? null
                                : () => _choose(
                                    question,
                                    answer.str('answer_id'),
                                  ),
                          ),
                        ),
                    ],
                  );
                },
              ),
            ),
            Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: Brand.line, width: 2)),
              ),
              padding: EdgeInsets.fromLTRB(
                16,
                12,
                16,
                12 + MediaQuery.of(context).padding.bottom,
              ),
              child: Row(
                children: [
                  if (_index > 0)
                    Expanded(
                      child: WebButton(
                        context.tr('Back'),
                        kind: BtnKind.ghost,
                        onPressed: () async => _pages.previousPage(
                          duration: const Duration(milliseconds: 250),
                          curve: Curves.easeOut,
                        ),
                      ),
                    )
                  else
                    const Spacer(),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _index < _questions.length - 1
                        ? WebButton(
                            context.tr('Next'),
                            onPressed: () async => _pages.nextPage(
                              duration: const Duration(milliseconds: 250),
                              curve: Curves.easeOut,
                            ),
                          )
                        : WebButton(
                            context.tr('Submit answers'),
                            kind: BtnKind.brand,
                            onPressed: _submitting ? null : () => _submit(),
                          ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AnswerOption extends StatelessWidget {
  const _AnswerOption({
    required this.text,
    required this.selected,
    required this.multiple,
    this.onTap,
  });

  final String text;
  final bool selected;
  final bool multiple;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? Brand.yellowWash : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: selected ? Brand.ink : Brand.line, width: 2),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Icon(
                multiple
                    ? (selected
                          ? Icons.check_box
                          : Icons.check_box_outline_blank)
                    : (selected
                          ? Icons.radio_button_checked
                          : Icons.radio_button_unchecked),
                color: Brand.ink,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  text,
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Result of an attempt (livewire/member/quizzes/result.blade.php).
class ResultScreen extends StatelessWidget {
  const ResultScreen({
    super.key,
    required this.quizId,
    this.result,
    this.attemptId,
  });

  final String quizId;
  final Map<String, dynamic>? result;
  final String? attemptId;

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () async =>
            result ??
            ((await context.api.get(
              'quizzes/$quizId/attempt/$attemptId/results',
            )) as Map).cast<String, dynamic>(),
        builder: (context, data, _) {
          final passed = data.flag('passed');
          final percent = data.number('percent').round();
          return MemberPage(
            children: [
              LinkText(
                context.tr('Quizzes'),
                onTap: () => context.go('/quizzes'),
              ),
              const SizedBox(height: 18),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(26),
                decoration: BoxDecoration(
                  color: passed ? Brand.yellow : Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: Brand.ink, width: 3),
                  boxShadow: const [
                    BoxShadow(color: Brand.ink, offset: Offset(6, 6)),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr(
                        passed ? 'You passed!' : 'Not passed this time',
                      ),
                      style: const TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '$percent%',
                      style: const TextStyle(
                        fontSize: 56,
                        fontWeight: FontWeight.w900,
                        height: 1.05,
                      ),
                    ),
                    Text(
                      context.tr(':score of :max marks', {
                        'score': data.number('score').round(),
                        'max': data.number('max_score').round(),
                      }),
                      style: const TextStyle(
                        color: Brand.charcoal,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (data.integer('points_awarded') > 0) ...[
                      const SizedBox(height: 14),
                      Tag(
                        '+${data.integer('points_awarded')} ${context.tr('points')}',
                        dark: true,
                      ),
                    ] else if (!data.flag('counts_as_result')) ...[
                      const SizedBox(height: 12),
                      Text(
                        context.tr(
                          'Your earlier result still counts, so no new points this time.',
                        ),
                        style: const TextStyle(color: Brand.charcoal),
                      ),
                    ],
                    if (data.flag('auto_submitted')) ...[
                      const SizedBox(height: 12),
                      Text(
                        context.tr(
                          'Time ran out, so your saved answers were submitted.',
                        ),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 28),
              if (!data.flag('answers_visible'))
                MistBox(
                  context.tr(
                    'Correct answers will be shown after the quiz closes.',
                  ),
                  color: Colors.white,
                  center: true,
                )
              else ...[
                SectionHeading(context.tr('Your answers')),
                const SizedBox(height: 14),
                for (final (index, question) in data.list('questions').indexed)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: WebCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(
                                question.flag('is_correct')
                                    ? Icons.check_circle
                                    : Icons.cancel,
                                color: question.flag('is_correct')
                                    ? Brand.success
                                    : Brand.danger,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  '${context.tr('Question :number', {'number': index + 1})}: ${question.str('question_text')}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 16,
                                    height: 1.35,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (question.strOrNull('explanation') != null) ...[
                            const SizedBox(height: 10),
                            Text(
                              question.str('explanation'),
                              style: const TextStyle(
                                color: Brand.slate,
                                height: 1.5,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
              ],
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  WebButton(
                    context.tr('Try again'),
                    expand: false,
                    onPressed: () async =>
                        context.pushReplacement('/quiz/$quizId'),
                  ),
                  WebButton(
                    context.tr('More quizzes'),
                    kind: BtnKind.ghost,
                    expand: false,
                    onPressed: () async => context.go('/quizzes'),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}

/// "Points and leaderboard" (livewire/member/leaderboard.blade.php).
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
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: ValueKey('$_scope-$_period'),
        load: () async {
          final api = context.api;
          final results = await Future.wait([
            api.get('leaderboard', query: {'period': _period, 'scope': _scope}),
            api.get('dashboard').catchError((_) => <String, dynamic>{}),
            api.get('badges').catchError((_) => <dynamic>[]),
          ]);
          return {
            ...(results[0] as Map).cast<String, dynamic>(),
            'points': (results[1] as Map)['total_points'] ?? 0,
            'badges': results[2],
          };
        },
        builder: (context, data, reload) {
          final entries = data.list('leaderboard');
          final me = data.obj('me');
          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(context.tr('Points and leaderboard')),
              const SizedBox(height: 24),
              StatBox(
                label: context.tr('Total points'),
                value: context.number(data.integer('points')),
              ),
              if (data.list('badges').isNotEmpty) ...[
                const SizedBox(height: 32),
                SectionHeading(context.tr('Badges')),
                const SizedBox(height: 14),
                BadgeGrid(badges: data.list('badges')),
              ],
              const SizedBox(height: 32),
              SectionHeading(context.tr('Leaderboard')),
              const SizedBox(height: 14),
              if (data.containsKey('enabled') && !data.flag('enabled'))
                MistBox(
                  context.tr('Leaderboards are switched off at the moment.'),
                  color: Colors.white,
                )
              else ...[
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final option in {
                      'national': 'Tanzania',
                      'region': 'My region',
                      'institution': 'My school',
                    }.entries)
                      ChoiceChip(
                        label: Text(context.tr(option.value)),
                        selected: _scope == option.key,
                        onSelected: (_) => setState(() => _scope = option.key),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final option in {
                      'all': 'All time',
                      'month': 'This month',
                      'week': 'This week',
                    }.entries)
                      ChoiceChip(
                        label: Text(context.tr(option.value)),
                        selected: _period == option.key,
                        onSelected: (_) => setState(() => _period = option.key),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                if (entries.isEmpty)
                  MistBox(
                    context.tr(
                      'No points earned in this period yet. Be the first!',
                    ),
                    color: Colors.white,
                    center: true,
                  ),
                if (entries.isNotEmpty)
                  WebCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: [
                        for (final entry in entries) _Row(entry: entry),
                        if (me != null &&
                            !entries.any((entry) => entry.flag('is_me')))
                          _Row(entry: me),
                      ],
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final rank = entry.integer('rank');
    return Container(
      color: entry.flag('is_me') ? Brand.yellowWash : null,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: rank <= 3 ? Brand.ink : Brand.mist,
              shape: BoxShape.circle,
            ),
            child: Text(
              '$rank',
              style: TextStyle(
                fontWeight: FontWeight.w900,
                color: rank <= 3 ? Brand.yellow : Brand.ink,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  entry.str('display_name'),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                if (entry.strOrNull('place') != null)
                  Text(
                    entry.str('place'),
                    style: const TextStyle(color: Brand.slate, fontSize: 13),
                  ),
              ],
            ),
          ),
          Text(
            context.number(entry.integer('points')),
            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
          ),
        ],
      ),
    );
  }
}
