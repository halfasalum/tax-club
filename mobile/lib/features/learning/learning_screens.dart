import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';
import '../site/site_screens.dart';

/// "Learn" (livewire/member/learn/index.blade.php): modules with progress
/// and a search over every lesson.
class LearningScreen extends StatefulWidget {
  const LearningScreen({super.key});

  @override
  State<LearningScreen> createState() => _LearningScreenState();
}

class _LearningScreenState extends State<LearningScreen> {
  final _search = TextEditingController();
  String _query = '';
  String? _type;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final api = context.api;
    final results = await Future.wait([
      api.get('learning'),
      api.getEnvelope(
        'content',
        query: {'search': _query, 'type': _type, 'per_page': 30},
      ),
    ]);
    return {
      ...(results[0] as Map).cast<String, dynamic>(),
      'lessons': (results[1] as Map)['data'],
    };
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: ValueKey('$_query-$_type'),
        load: _load,
        builder: (context, data, reload) {
          final modules = data.list('modules');
          final lessons = data.list('lessons');
          final resume = data.obj('resume');
          final done = data.integer('completed_lessons');

          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                context.tr('Learn'),
                subtitle: context.trPlural(
                  '{0} Start your first lesson today.|{1} You have completed :count lesson.|[2,*] You have completed :count lessons.',
                  done,
                ),
              ),
              if (resume != null) ...[
                const SizedBox(height: 24),
                WebCard(
                  highlight: true,
                  color: Brand.yellowWash,
                  onTap: () =>
                      context.push('/lesson/${resume.str('content_id')}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('Continue where you left off'),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: Brand.slate,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        resume.str('title'),
                        style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 10),
                      LinkText(
                        context.tr('Continue'),
                        onTap: () =>
                            context.push('/lesson/${resume.str('content_id')}'),
                      ),
                    ],
                  ),
                ),
              ],
              if (modules.isNotEmpty) ...[
                const SizedBox(height: 36),
                SectionHeading(context.tr('Learning modules')),
                const SizedBox(height: 16),
                for (final module in modules)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: WebCard(
                      highlight:
                          module.integer('percent') > 0 &&
                          !module.flag('is_complete'),
                      onTap: () =>
                          context.push('/learn/module/${module.integer('id')}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              if (module.strOrNull('category') != null)
                                Expanded(
                                  child: Text(
                                    module.str('category'),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: Brand.slate,
                                      fontSize: 14,
                                    ),
                                  ),
                                ),
                              if (module.flag('is_complete'))
                                Tag(context.tr('Completed')),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            module.str('title'),
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          if (module.strOrNull('summary') != null) ...[
                            const SizedBox(height: 8),
                            Text(
                              module.str('summary'),
                              style: const TextStyle(
                                color: Brand.slate,
                                fontSize: 16,
                                height: 1.5,
                              ),
                            ),
                          ],
                          const SizedBox(height: 16),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: LinearProgressIndicator(
                              value: module.integer('percent') / 100,
                              minHeight: 10,
                              backgroundColor: Brand.mist,
                              color: Brand.ink,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            context.tr(':done of :total lessons', {
                              'done': module.integer('completed_lessons'),
                              'total': module.integer('required_lessons'),
                            }),
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
              const SizedBox(height: 36),
              SectionHeading(context.tr('All lessons')),
              const SizedBox(height: 16),
              TextField(
                controller: _search,
                textInputAction: TextInputAction.search,
                onSubmitted: (value) => setState(() => _query = value.trim()),
                decoration: InputDecoration(
                  hintText: context.tr('Search lessons, e.g. VAT or receipts'),
                  prefixIcon: const Icon(Icons.search),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String?>(
                initialValue: _type,
                decoration: const InputDecoration(),
                items: [
                  DropdownMenuItem(
                    value: null,
                    child: Text(context.tr('All types')),
                  ),
                  for (final type in optionsOf(
                    context,
                    'content_types',
                  ).entries)
                    DropdownMenuItem(value: type.key, child: Text(type.value)),
                ],
                onChanged: (value) => setState(() => _type = value),
              ),
              const SizedBox(height: 18),
              if (lessons.isEmpty)
                MistBox(
                  context.tr('No lessons match your search.'),
                  color: Colors.white,
                ),
              for (final lesson in lessons)
                Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: MemberLessonCard(lesson: lesson),
                ),
            ],
          );
        },
      ),
    );
  }
}

/// Lesson card with type, topic and reading time.
class MemberLessonCard extends StatelessWidget {
  const MemberLessonCard({super.key, required this.lesson, this.status});

  final Map<String, dynamic> lesson;
  final String? status;

  @override
  Widget build(BuildContext context) {
    final minutes = lesson.intOrNull('estimated_minutes');
    final topic =
        lesson.obj('category')?.str('name') ?? lesson.strOrNull('category');
    final state = status ?? lesson.obj('progress')?.str('status');
    return WebCard(
      onTap: () => context.push('/lesson/${lesson.str('content_id')}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Tag(
                labelOf(context, 'content_types', lesson.str('content_type')),
              ),
              Text(
                [
                  ?topic,
                  if (minutes != null)
                    context.tr(':minutes min', {'minutes': minutes}),
                ].join(' · '),
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  color: Brand.slate,
                  fontSize: 14,
                ),
              ),
              if (state == 'completed')
                Tag(context.tr('Completed'), color: const Color(0xFFDCFCE7)),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            lesson.str('title'),
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              height: 1.3,
            ),
          ),
          if (lesson.strOrNull('summary') != null) ...[
            const SizedBox(height: 8),
            Text(
              lesson.str('summary'),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Brand.slate,
                fontSize: 16,
                height: 1.5,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class ModuleScreen extends StatelessWidget {
  const ModuleScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () async =>
            ((await context.api.get('learning/modules/$id')) as Map)
                .cast<String, dynamic>(),
        builder: (context, module, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              module.str('title'),
              subtitle: module.strOrNull('summary'),
              back: context.tr('Learn'),
              onBack: () => context.go('/learn'),
            ),
            const SizedBox(height: 22),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: module.integer('percent') / 100,
                minHeight: 12,
                backgroundColor: Colors.white,
                color: Brand.ink,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              context.tr(':done of :total lessons', {
                'done': module.integer('completed_lessons'),
                'total': module.integer('required_lessons'),
              }),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            if (module.flag('is_complete')) ...[
              const SizedBox(height: 18),
              WebCard(
                highlight: true,
                color: Brand.yellowWash,
                onTap: () => context.go('/achievements'),
                child: Text(
                  context.tr(
                    'Module complete! Your certificate is in My achievements.',
                  ),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ],
            const SizedBox(height: 24),
            for (final (index, lesson) in module.list('lessons').indexed)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: WebCard(
                  onTap: () =>
                      context.push('/lesson/${lesson.str('content_id')}'),
                  child: Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: lesson.str('status') == 'completed'
                              ? Brand.success
                              : Brand.ink,
                          shape: BoxShape.circle,
                        ),
                        child: lesson.str('status') == 'completed'
                            ? const Icon(
                                Icons.check,
                                color: Colors.white,
                                size: 22,
                              )
                            : Text(
                                '${index + 1}',
                                style: const TextStyle(
                                  color: Brand.yellow,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 17,
                                ),
                              ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              lesson.str('title'),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                                height: 1.3,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              [
                                labelOf(
                                  context,
                                  'content_types',
                                  lesson.str('content_type'),
                                ),
                                if (lesson.intOrNull('estimated_minutes') !=
                                    null)
                                  context.tr(':minutes min', {
                                    'minutes': lesson.integer(
                                      'estimated_minutes',
                                    ),
                                  }),
                              ].join(' · '),
                              style: const TextStyle(
                                color: Brand.slate,
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right),
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

/// A lesson (livewire/member/learn/lesson.blade.php).
class LessonScreen extends StatefulWidget {
  const LessonScreen({super.key, required this.id});

  final String id;

  @override
  State<LessonScreen> createState() => _LessonScreenState();
}

class _LessonScreenState extends State<LessonScreen> {
  bool _completed = false;

  Future<Map<String, dynamic>> _load() async {
    final api = context.api;
    final data = ((await api.get('content/${widget.id}')) as Map)
        .cast<String, dynamic>();
    _completed = data.obj('progress')?.str('status') == 'completed';
    // Opening a lesson starts its progress; failures are not shown.
    unawaited(
      api.post('content/${widget.id}/open').then((_) {}, onError: (_) {}),
    );
    return data;
  }

  Future<void> _complete() async {
    final result = await runAction(
      context,
      () => context.api.post('content/${widget.id}/complete'),
      showServerMessage: true,
    );
    if (result != null && mounted) setState(() => _completed = true);
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: _load,
        builder: (context, data, reload) {
          final content = data.obj('content') ?? const {};
          final objectives = content.strings('objectives');
          final type = content.str('content_type');
          final fileUrl = content.strOrNull('file_url');
          final embedUrl = content.strOrNull('embed_url');
          final quizId = content.strOrNull('quiz_id');

          return MemberPage(
            children: [
              LinkText(context.tr('Learn'), onTap: () => context.go('/learn')),
              const SizedBox(height: 16),
              Text(
                content.str('title'),
                style: const TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 10,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Tag(labelOf(context, 'content_types', type)),
                  if (content.intOrNull('estimated_minutes') != null)
                    Text(
                      context.tr(':minutes min', {
                        'minutes': content.integer('estimated_minutes'),
                      }),
                      style: const TextStyle(
                        color: Brand.slate,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                  if (content.intOrNull('version') != null)
                    Text(
                      context.tr('Version :version', {
                        'version': content.integer('version'),
                      }),
                      style: const TextStyle(
                        color: Brand.slate,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                ],
              ),
              if (objectives.isNotEmpty) ...[
                const SizedBox(height: 24),
                WebCard(
                  highlight: true,
                  color: Brand.yellowWash,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr('In this lesson you will learn'),
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 17,
                        ),
                      ),
                      Bullets(objectives),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 24),
              if (type == 'infographic' && fileUrl != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: Image.network(
                    fileUrl,
                    errorBuilder: (_, _, _) => const SizedBox.shrink(),
                  ),
                ),
              if (embedUrl != null || (type == 'video' && fileUrl != null))
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: WebButton(
                    context.tr('Watch the video'),
                    icon: Icons.play_arrow,
                    onPressed: () => openLink(context, embedUrl ?? fileUrl),
                  ),
                ),
              if (type == 'document' && fileUrl != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: WebButton(
                    context.tr('Open the document'),
                    kind: BtnKind.ghost,
                    icon: Icons.description_outlined,
                    onPressed: () => openLink(context, fileUrl),
                  ),
                ),
              LessonBody(html: content.str('body_html')),
              const SizedBox(height: 40),
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border.all(color: Brand.line, width: 2),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_completed)
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(5),
                            decoration: const BoxDecoration(
                              color: Brand.success,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.check,
                              color: Colors.white,
                              size: 22,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              context.tr('You completed this lesson'),
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                                color: Brand.success,
                              ),
                            ),
                          ),
                        ],
                      )
                    else ...[
                      Text(
                        context.tr('Finished reading?'),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 12),
                      WebButton(
                        context.tr('Mark as complete'),
                        expand: false,
                        onPressed: _complete,
                      ),
                    ],
                    const SizedBox(height: 20),
                    Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: [
                        if (quizId != null)
                          WebButton(
                            context.tr('Take the quiz'),
                            kind: BtnKind.brand,
                            expand: false,
                            onPressed: () async =>
                                context.push('/quiz/$quizId'),
                          ),
                        WebButton(
                          context.tr('More lessons'),
                          kind: BtnKind.ghost,
                          expand: false,
                          onPressed: () async => context.go('/learn'),
                        ),
                      ],
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
