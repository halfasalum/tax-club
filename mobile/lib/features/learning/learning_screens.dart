import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

IconData contentIcon(String type) => switch (type) {
      'video' => Icons.play_circle_outline,
      'infographic' => Icons.image_outlined,
      'document' => Icons.description_outlined,
      _ => Icons.article_outlined,
    };

/// Learning hub: modules with progress, and a search over all lessons.
class LearningScreen extends StatefulWidget {
  const LearningScreen({super.key});

  @override
  State<LearningScreen> createState() => _LearningScreenState();
}

class _LearningScreenState extends State<LearningScreen> {
  final _search = TextEditingController();
  final _results = GlobalKey<PagedListState<Map<String, dynamic>>>();
  String _query = '';
  Timer? _debounce;

  @override
  void dispose() {
    _search.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      setState(() => _query = value.trim());
      _results.currentState?.refresh();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Learn')),
        actions: [IconButton(tooltip: context.tr('Quizzes'), onPressed: () => context.push('/quizzes'), icon: const Icon(Icons.quiz_outlined))],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: TextField(
              controller: _search,
              onChanged: _onSearch,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: context.tr('Search lessons, e.g. VAT or TIN'),
                prefixIcon: const Icon(Icons.search),
                isDense: true,
                suffixIcon: _query.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () {
                          _search.clear();
                          setState(() => _query = '');
                        },
                      ),
              ),
            ),
          ),
        ),
      ),
      body: _query.isNotEmpty
          ? PagedList<Map<String, dynamic>>(
              key: _results,
              load: (page) => context.api.paged('content', (row) => row, query: {'search': _query, 'page': page}),
              empty: EmptyPanel(message: context.tr('No lessons match your search.'), icon: Icons.search_off),
              itemBuilder: (context, lesson, _) => LessonTile(lesson: lesson, status: lesson.obj('progress')?.str('status')),
            )
          : Loader<Map<String, dynamic>>(
              load: () async => ((await context.api.get('learning')) as Map).cast<String, dynamic>(),
              builder: (context, data, reload) {
                final modules = data.list('modules');
                final resume = data.obj('resume');

                return RefreshIndicator(
                  onRefresh: reload,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (resume != null) ...[
                        Tile(
                          color: Brand.yellow,
                          onTap: () => context.push('/lesson/${resume.str('content_id')}'),
                          child: Row(children: [
                            const Icon(Icons.play_circle_fill, size: 36),
                            const SizedBox(width: 12),
                            Expanded(child: Text(context.tr('Continue: :title', {'title': resume.str('title')}), style: const TextStyle(fontWeight: FontWeight.w700))),
                          ]),
                        ),
                        const SizedBox(height: 12),
                      ],
                      Text(
                        context.trPlural('You have completed :count lesson.|You have completed :count lessons.', data.integer('completed_lessons')),
                        style: const TextStyle(color: Brand.slate),
                      ),
                      const SizedBox(height: 8),
                      if (modules.isEmpty) EmptyPanel(message: context.tr('No learning modules yet. Check back soon.'), icon: Icons.school_outlined),
                      for (final module in modules)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Tile(
                            onTap: () => context.push('/learn/module/${module.integer('id')}'),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Row(children: [
                                if (module.strOrNull('category') != null) Pill(module.str('category')),
                                const Spacer(),
                                if (module.flag('is_complete')) Pill.tone(context.tr('Completed'), 'good'),
                              ]),
                              const SizedBox(height: 8),
                              Text(module.str('title'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                              if (module.strOrNull('summary') != null) ...[
                                const SizedBox(height: 4),
                                Text(module.str('summary'), maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.slate)),
                              ],
                              const SizedBox(height: 12),
                              ClipRRect(
                                borderRadius: BorderRadius.circular(6),
                                child: LinearProgressIndicator(value: module.integer('percent') / 100, minHeight: 8, backgroundColor: Brand.line, color: Brand.ink),
                              ),
                              const SizedBox(height: 6),
                              Text(context.tr(':done of :total lessons', {'done': module.integer('completed_lessons'), 'total': module.integer('required_lessons')}),
                                  style: const TextStyle(fontSize: 12, color: Brand.slate)),
                            ]),
                          ),
                        ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}

class LessonTile extends StatelessWidget {
  const LessonTile({super.key, required this.lesson, this.status});

  final Map<String, dynamic> lesson;
  final String? status;

  @override
  Widget build(BuildContext context) {
    final minutes = lesson.intOrNull('estimated_minutes');
    return Tile(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      onTap: () => context.push('/lesson/${lesson.str('content_id')}'),
      child: Row(children: [
        Icon(
          status == 'completed' ? Icons.check_circle : contentIcon(lesson.str('content_type')),
          color: status == 'completed' ? Brand.success : Brand.ink,
          size: 30,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(lesson.str('title'), style: const TextStyle(fontWeight: FontWeight.w600)),
            if (lesson.strOrNull('summary') != null)
              Text(lesson.str('summary'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.slate, fontSize: 13)),
            if (minutes != null) Text(context.tr(':count min', {'count': minutes}), style: const TextStyle(color: Brand.slate, fontSize: 12)),
          ]),
        ),
        if (status == 'in_progress') Pill.tone(context.tr('In progress'), 'info'),
      ]),
    );
  }
}

class ModuleScreen extends StatelessWidget {
  const ModuleScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Module'))),
      body: Loader<Map<String, dynamic>>(
        load: () async => ((await context.api.get('learning/modules/$id')) as Map).cast<String, dynamic>(),
        builder: (context, module, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(module.str('title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              Paragraphs(module.strOrNull('summary')),
              const SizedBox(height: 12),
              LinearProgressIndicator(value: module.integer('percent') / 100, minHeight: 8, backgroundColor: Brand.line, color: Brand.ink),
              const SizedBox(height: 6),
              Text(context.tr(':done of :total lessons', {'done': module.integer('completed_lessons'), 'total': module.integer('required_lessons')})),
              if (module.flag('is_complete')) ...[
                const SizedBox(height: 12),
                Tile(
                  color: Brand.yellowWash,
                  onTap: () => context.push('/achievements'),
                  child: Text(context.tr('Module complete! Your certificate is in My achievements.')),
                ),
              ],
              const SizedBox(height: 16),
              for (final (index, lesson) in module.list('lessons').indexed)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    SizedBox(width: 28, child: Padding(padding: const EdgeInsets.only(top: 18), child: Text('${index + 1}.', style: const TextStyle(fontWeight: FontWeight.w700)))),
                    Expanded(child: LessonTile(lesson: lesson, status: lesson.str('status'))),
                  ]),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

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
    final data = ((await api.get('content/${widget.id}')) as Map).cast<String, dynamic>();
    _completed = data.obj('progress')?.str('status') == 'completed';
    // Opening a lesson starts its progress; failures are not shown.
    unawaited(api.post('content/${widget.id}/open').then((_) {}, onError: (_) {}));
    return data;
  }

  Future<void> _complete() async {
    final result = await runAction(context, () => context.api.post('content/${widget.id}/complete'), showServerMessage: true);
    if (result != null && mounted) setState(() => _completed = true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Lesson'))),
      body: Loader<Map<String, dynamic>>(
        load: _load,
        builder: (context, data, reload) {
          final content = data.obj('content') ?? const {};
          final objectives = content.strings('objectives');
          final type = content.str('content_type');
          final fileUrl = content.strOrNull('file_url');
          final embedUrl = content.strOrNull('embed_url');
          final quizId = content.strOrNull('quiz_id');

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Wrap(spacing: 8, runSpacing: 8, children: [
                Pill(labelOf(context, 'content_types', type)),
                if (content.obj('category') != null) Pill(content.obj('category')!.str('name')),
                if (content.intOrNull('estimated_minutes') != null) Pill(context.tr(':count min', {'count': content.integer('estimated_minutes')})),
              ]),
              const SizedBox(height: 12),
              Text(content.str('title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              if (content.strOrNull('summary') != null) ...[
                const SizedBox(height: 8),
                Text(content.str('summary'), style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Brand.slate)),
              ],
              if (objectives.isNotEmpty) ...[
                const SizedBox(height: 16),
                Tile(
                  color: Brand.yellowWash,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(context.tr('In this lesson you will learn'), style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 6),
                    for (final objective in objectives) InfoRow(icon: Icons.check, text: objective),
                  ]),
                ),
              ],
              const SizedBox(height: 16),
              if (type == 'infographic' && fileUrl != null)
                ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(fileUrl, errorBuilder: (_, _, _) => const SizedBox.shrink())),
              if (embedUrl != null || (type == 'video' && fileUrl != null))
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: BusyButton(label: context.tr('Watch the video'), icon: Icons.play_arrow, onPressed: () => openLink(context, embedUrl ?? fileUrl)),
                ),
              if (type == 'document' && fileUrl != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: BusyButton(label: context.tr('Open the document'), icon: Icons.description_outlined, outlined: true, onPressed: () => openLink(context, fileUrl)),
                ),
              HtmlWidget(
                content.str('body_html'),
                textStyle: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.55),
                onTapUrl: (url) {
                  openLink(context, url);
                  return true;
                },
              ),
              const SizedBox(height: 24),
              if (_completed)
                Tile(color: Brand.yellowWash, child: Row(children: [
                  const Icon(Icons.check_circle, color: Brand.success),
                  const SizedBox(width: 8),
                  Expanded(child: Text(context.tr('You have completed this lesson.'))),
                ]))
              else
                BusyButton(label: context.tr('Mark as complete'), icon: Icons.check, onPressed: _complete),
              if (quizId != null) ...[
                const SizedBox(height: 12),
                BusyButton(label: context.tr('Take the quiz'), icon: Icons.quiz_outlined, outlined: true, onPressed: () async => context.push('/quiz/$quizId')),
              ],
              const SizedBox(height: 24),
            ],
          );
        },
      ),
    );
  }
}
