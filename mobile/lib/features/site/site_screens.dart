import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// The public website, page for page (resources/views/pages, learn,
/// events, answers), for visitors who have not signed in.

Widget _pad(Widget child, {double top = 0}) =>
    Padding(padding: EdgeInsets.fromLTRB(16, top, 16, 0), child: child);

class _HeroTitle extends StatelessWidget {
  const _HeroTitle(this.title, this.text, {this.extra = const []});

  final String title;
  final String? text;
  final List<Widget> extra;

  @override
  Widget build(BuildContext context) {
    return Hatch(
      padding: const EdgeInsets.fromLTRB(16, 44, 16, 44),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 36,
              fontWeight: FontWeight.w900,
              height: 1.08,
              letterSpacing: -0.6,
            ),
          ),
          if (text != null) ...[
            const SizedBox(height: 14),
            Text(
              text!,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: Brand.charcoal,
                height: 1.55,
              ),
            ),
          ],
          ...extra,
        ],
      ),
    );
  }
}

// ─── Home ──────────────────────────────────────────────────────────

class SiteHomeScreen extends StatefulWidget {
  const SiteHomeScreen({super.key});

  @override
  State<SiteHomeScreen> createState() => _SiteHomeScreenState();
}

class _SiteHomeScreenState extends State<SiteHomeScreen> {
  Future<Map<String, dynamic>>? _home;

  Future<Map<String, dynamic>> _load() => _home ??= context.api
      .get('public/home')
      .then((data) => (data as Map).cast<String, dynamic>());

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      children: [
        Hatch(
          padding: const EdgeInsets.fromLTRB(16, 44, 16, 48),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.fromLTRB(6, 5, 14, 5),
                decoration: BoxDecoration(
                  color: Brand.ink,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(1),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(3),
                      ),
                      child: Image.asset(
                        'assets/images/tra-logo.png',
                        height: 18,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        context.tr('Tanzania Revenue Authority'),
                        style: const TextStyle(
                          color: Brand.yellow,
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              Text(
                context.tr('Taxes build our schools, roads and hospitals.'),
                style: const TextStyle(
                  fontSize: 44,
                  fontWeight: FontWeight.w900,
                  height: 1.02,
                  letterSpacing: -1.1,
                ),
              ),
              const SizedBox(height: 22),
              Text(
                context.tr(
                  'Tax Club brings students in primary and secondary schools, colleges and universities together to learn how taxation works, test what they know and take part in national activities run by TRA.',
                ),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Brand.charcoal,
                  height: 1.6,
                ),
              ),
              const SizedBox(height: 28),
              WebButton(
                context.tr('Join Tax Club'),
                expand: false,
                large: true,
                onPressed: () async => context.push('/register'),
              ),
              const SizedBox(height: 12),
              WebButton(
                context.tr('Browse lessons'),
                kind: BtnKind.ghost,
                expand: false,
                large: true,
                onPressed: () async => context.go('/site/learn'),
              ),
              const SizedBox(height: 28),
              TickLine(context.tr('Free for students')),
              TickLine(context.tr('Kiswahili and English')),
              TickLine(context.tr('Digital membership card')),
              _Counts(future: _load()),
              const SizedBox(height: 36),
              const ReceiptHero(),
            ],
          ),
        ),
        _pad(
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.tr('How to join'),
                style: const TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 28),
              for (final (index, step) in [
                (
                  'Register',
                  'Sign up on the website or the Tax Club app and choose your school, college or university.',
                ),
                (
                  'Get confirmed',
                  'Your Tax Club patron or TRA coordinator confirms that you are a member.',
                ),
                (
                  'Start learning',
                  'Receive your digital membership card, take lessons and quizzes, and join events.',
                ),
              ].indexed)
                Padding(
                  padding: const EdgeInsets.only(bottom: 28),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 56,
                        height: 56,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: Brand.ink,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 4),
                          boxShadow: const [
                            BoxShadow(color: Color(0x22000000), blurRadius: 3),
                          ],
                        ),
                        child: Text(
                          '${index + 1}',
                          style: const TextStyle(
                            color: Brand.yellow,
                            fontSize: 24,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),
                      Text(
                        context.tr(step.$1),
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        context.tr(step.$2),
                        style: const TextStyle(
                          fontSize: 18,
                          color: Brand.slate,
                          height: 1.6,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
          top: 56,
        ),
        Loader<Map<String, dynamic>>(
          load: () {
            _home = null;
            return _load();
          },
          builder: (context, data, _) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _pad(
                SectionHeading(
                  context.tr('Latest lessons'),
                  size: 30,
                  link: context.tr('All lessons'),
                  onLink: () => context.go('/site/learn'),
                ),
                top: 28,
              ),
              const SizedBox(height: 24),
              if (data.list('lessons').isEmpty)
                _pad(
                  MistBox(
                    context.tr(
                      'Lessons will appear here as soon as TRA publishes them.',
                    ),
                  ),
                )
              else
                for (final lesson in data.list('lessons'))
                  _pad(
                    Padding(
                      padding: const EdgeInsets.only(bottom: 18),
                      child: _lessonCard(context, lesson),
                    ),
                  ),
              _pad(
                SectionHeading(
                  context.tr('Upcoming events'),
                  size: 30,
                  link: context.tr('All events'),
                  onLink: () => context.go('/site/events'),
                ),
                top: 48,
              ),
              const SizedBox(height: 24),
              if (data.list('events').isEmpty)
                _pad(
                  MistBox(
                    context.tr(
                      'No events are scheduled yet. Check back soon or join to be notified.',
                    ),
                  ),
                )
              else
                for (final event in data.list('events'))
                  _pad(
                    Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: _eventRow(context, event),
                    ),
                  ),
            ],
          ),
        ),
        _pad(
          DarkPanel(
            topBorder: true,
            title: context.tr('Is your school starting a Tax Club?'),
            text: context.tr(
              'Patrons can register their institution with the TRA Tax Club coordinator in their region, then verify student members online.',
            ),
            actions: [
              WebButton(
                context.tr('Information for patrons'),
                kind: BtnKind.brand,
                expand: false,
                onPressed: () async => context.go('/site/about'),
              ),
            ],
          ),
          top: 64,
        ),
      ],
    );
  }
}

class _Counts extends StatelessWidget {
  const _Counts({required this.future});

  final Future<Map<String, dynamic>> future;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: future,
      builder: (context, snapshot) {
        final data = snapshot.data;
        if (data == null ||
            data.integer('member_count') == 0 ||
            data.integer('institution_count') == 0) {
          return const SizedBox.shrink();
        }
        return Padding(
          padding: const EdgeInsets.only(top: 14),
          child: Text(
            '${context.trPlural(':count member|:count members', data.integer('member_count'), {'count': context.number(data.integer('member_count'))})}, '
            '${context.trPlural(':count institution|:count institutions', data.integer('institution_count'), {'count': context.number(data.integer('institution_count'))})}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: Brand.charcoal,
            ),
          ),
        );
      },
    );
  }
}

Widget _lessonCard(BuildContext context, Map<String, dynamic> lesson) =>
    LessonCardView(
      type: lesson.str('content_type_label'),
      title: lesson.str('title'),
      summary: lesson.strOrNull('summary'),
      date: lesson.date('published_at'),
      onTap: () => context.push('/site/learn/${lesson.str('slug')}'),
    );

Widget _eventRow(BuildContext context, Map<String, dynamic> event) =>
    EventRowView(
      title: event.str('title'),
      startsAt: event.date('starts_at'),
      location: event.strOrNull('location'),
      onTap: () => context.push('/site/events/${event.str('slug')}'),
    );

/// "Join Tax Club" / "Sign in" panel at the end of public pages.
class _JoinPanel extends StatelessWidget {
  const _JoinPanel({
    required this.title,
    required this.text,
    this.signInFirst = false,
  });

  final String title;
  final String text;
  final bool signInFirst;

  @override
  Widget build(BuildContext context) {
    final join = WebButton(
      context.tr('Join Tax Club'),
      kind: signInFirst ? BtnKind.onDark : BtnKind.brand,
      expand: false,
      onPressed: () async => context.push('/register'),
    );
    final signIn = WebButton(
      context.tr('Sign in'),
      kind: signInFirst ? BtnKind.brand : BtnKind.onDark,
      expand: false,
      onPressed: () async => context.push('/login'),
    );
    return DarkPanel(
      title: title,
      text: text,
      actions: signInFirst ? [signIn, join] : [join, signIn],
    );
  }
}

// ─── Lessons ───────────────────────────────────────────────────────

class SiteLearnScreen extends StatefulWidget {
  const SiteLearnScreen({super.key});

  @override
  State<SiteLearnScreen> createState() => _SiteLearnScreenState();
}

class _SiteLearnScreenState extends State<SiteLearnScreen> {
  final _search = TextEditingController();
  String _query = '';
  int _page = 1;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'learn',
      children: [
        _HeroTitle(
          context.tr('Tax lessons'),
          context.tr(
            'Short, practical lessons prepared by TRA. Members can track progress and take quizzes after signing in.',
          ),
          extra: [
            const SizedBox(height: 28),
            ShadowSearch(
              controller: _search,
              hint: context.tr('Search lessons, for example VAT'),
              onSearch: () => setState(() {
                _query = _search.text.trim();
                _page = 1;
              }),
            ),
          ],
        ),
        Loader<Paged<Map<String, dynamic>>>(
          key: ValueKey('$_query-$_page'),
          load: () => context.api.paged(
            'public/lessons',
            (row) => row,
            query: {'search': _query, 'page': _page},
          ),
          builder: (context, page, _) => _pad(
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (page.items.isEmpty)
                  _query.isEmpty
                      ? MistBox(
                          context.tr(
                            'Lessons will appear here as soon as TRA publishes them.',
                          ),
                        )
                      : Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(28),
                          decoration: BoxDecoration(
                            color: Brand.mist,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                context.tr('No lessons match “:term”.', {
                                  'term': _query,
                                }),
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 10),
                              LinkText(
                                context.tr('see all lessons'),
                                onTap: () => setState(() {
                                  _search.clear();
                                  _query = '';
                                }),
                              ),
                            ],
                          ),
                        )
                else ...[
                  if (_query.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 20),
                      child: Text(
                        context.trPlural(
                          ':count lesson found|:count lessons found',
                          page.total,
                        ),
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: Brand.slate,
                        ),
                      ),
                    ),
                  for (final lesson in page.items)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 18),
                      child: _lessonCard(context, lesson),
                    ),
                  _Pager(
                    page: page,
                    onPage: (value) => setState(() => _page = value),
                  ),
                ],
              ],
            ),
            top: 40,
          ),
        ),
      ],
    );
  }
}

class _Pager extends StatelessWidget {
  const _Pager({required this.page, required this.onPage});

  final Paged<dynamic> page;
  final ValueChanged<int> onPage;

  @override
  Widget build(BuildContext context) {
    if (page.lastPage <= 1) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Row(
        children: [
          if (page.currentPage > 1)
            WebButton(
              context.tr('Previous'),
              kind: BtnKind.ghost,
              expand: false,
              onPressed: () async => onPage(page.currentPage - 1),
            ),
          const Spacer(),
          Text(
            '${page.currentPage} / ${page.lastPage}',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          const Spacer(),
          if (page.hasMore)
            WebButton(
              context.tr('Next'),
              expand: false,
              onPressed: () async => onPage(page.currentPage + 1),
            ),
        ],
      ),
    );
  }
}

class SiteLessonScreen extends StatelessWidget {
  const SiteLessonScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'learn',
      children: [
        Loader<Map<String, dynamic>>(
          load: () async =>
              ((await context.api.get('public/lessons/$slug')) as Map)
                  .cast<String, dynamic>(),
          builder: (context, lesson, _) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Hatch(
                padding: const EdgeInsets.fromLTRB(16, 36, 16, 40),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        LinkText(
                          context.tr('Learn'),
                          onTap: () => context.go('/site/learn'),
                        ),
                        const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 8),
                          child: Text(
                            '/',
                            style: TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                        Tag(lesson.str('content_type_label'), dark: true),
                      ],
                    ),
                    const SizedBox(height: 20),
                    Text(
                      lesson.str('title'),
                      style: const TextStyle(
                        fontSize: 34,
                        fontWeight: FontWeight.w900,
                        height: 1.1,
                        letterSpacing: -0.5,
                      ),
                    ),
                    if (lesson.strOrNull('summary') != null) ...[
                      const SizedBox(height: 14),
                      Text(
                        lesson.str('summary'),
                        style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w600,
                          color: Brand.charcoal,
                          height: 1.5,
                        ),
                      ),
                    ],
                    if (lesson.date('published_at') != null) ...[
                      const SizedBox(height: 18),
                      Text(
                        '${context.tr('Published')} ${context.date(lesson.date('published_at'), pattern: 'd MMMM y')}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                          color: Brand.charcoal,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              _pad(
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (lesson.strOrNull('embed_url') != null ||
                        (lesson.str('content_type') == 'video' &&
                            lesson.strOrNull('file_url') != null))
                      Padding(
                        padding: const EdgeInsets.only(bottom: 20),
                        child: WebButton(
                          context.tr('Watch the video'),
                          icon: Icons.play_arrow,
                          onPressed: () => openLink(
                            context,
                            lesson.strOrNull('embed_url') ??
                                lesson.str('file_url'),
                          ),
                        ),
                      ),
                    if (lesson.str('content_type') == 'infographic' &&
                        lesson.strOrNull('file_url') != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 20),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: Image.network(
                            lesson.str('file_url'),
                            errorBuilder: (_, _, _) => const SizedBox.shrink(),
                          ),
                        ),
                      ),
                    if (lesson.str('content_type') == 'document' &&
                        lesson.strOrNull('file_url') != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 20),
                        child: WebButton(
                          context.tr('Open the document'),
                          kind: BtnKind.ghost,
                          icon: Icons.description_outlined,
                          onPressed: () =>
                              openLink(context, lesson.str('file_url')),
                        ),
                      ),
                    LessonBody(html: lesson.str('body_html')),
                    const SizedBox(height: 48),
                    _JoinPanel(
                      title: context.tr('Test what you learned'),
                      text: context.tr(
                        'Members can take quizzes, earn points and get certificates.',
                      ),
                    ),
                  ],
                ),
                top: 32,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Lesson text with the website's "prose" styling.
class LessonBody extends StatelessWidget {
  const LessonBody({super.key, required this.html});

  final String html;

  @override
  Widget build(BuildContext context) {
    return HtmlWidget(
      html,
      textStyle: const TextStyle(
        fontSize: 18,
        height: 1.65,
        color: Brand.charcoal,
      ),
      customStylesBuilder: (element) => switch (element.localName) {
        'h1' || 'h2' || 'h3' || 'h4' => {
          'font-weight': '900',
          'color': '#020202',
          'line-height': '1.3',
        },
        'strong' || 'b' => {'color': '#020202', 'font-weight': '800'},
        'a' => {'color': '#020202', 'font-weight': '700'},
        'blockquote' => {
          'border-left': '4px solid #f9e50f',
          'padding-left': '16px',
          'margin': '0',
        },
        _ => null,
      },
      onTapUrl: (url) {
        openLink(context, url);
        return true;
      },
    );
  }
}

// ─── Events ────────────────────────────────────────────────────────

class SiteEventsScreen extends StatefulWidget {
  const SiteEventsScreen({super.key});

  @override
  State<SiteEventsScreen> createState() => _SiteEventsScreenState();
}

class _SiteEventsScreenState extends State<SiteEventsScreen> {
  int _page = 1;

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'events',
      children: [
        _HeroTitle(
          context.tr('Tax Club events'),
          context.tr(
            'Competitions, school visits and campaigns. Members register for events after signing in.',
          ),
        ),
        Loader<Paged<Map<String, dynamic>>>(
          key: ValueKey(_page),
          load: () => context.api.paged(
            'public/events',
            (row) => row,
            query: {'page': _page},
          ),
          builder: (context, page, _) => _pad(
            Column(
              children: [
                if (page.items.isEmpty)
                  MistBox(
                    context.tr(
                      'No events are scheduled yet. Check back soon or join to be notified.',
                    ),
                  ),
                for (final event in page.items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: _eventRow(context, event),
                  ),
                _Pager(
                  page: page,
                  onPage: (value) => setState(() => _page = value),
                ),
              ],
            ),
            top: 40,
          ),
        ),
      ],
    );
  }
}

class SiteEventScreen extends StatelessWidget {
  const SiteEventScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'events',
      children: [
        Loader<Map<String, dynamic>>(
          load: () async =>
              ((await context.api.get('public/events/$slug')) as Map)
                  .cast<String, dynamic>(),
          builder: (context, event, _) {
            final starts = event.date('starts_at');
            final ends = event.date('ends_at');
            final sameDay =
                starts != null &&
                ends != null &&
                starts.year == ends.year &&
                starts.month == ends.month &&
                starts.day == ends.day;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Hatch(
                  padding: const EdgeInsets.fromLTRB(16, 36, 16, 40),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      LinkText(
                        context.tr('Events'),
                        onTap: () => context.go('/site/events'),
                      ),
                      const SizedBox(height: 20),
                      Text(
                        event.str('title'),
                        style: const TextStyle(
                          fontSize: 34,
                          fontWeight: FontWeight.w900,
                          height: 1.1,
                          letterSpacing: -0.5,
                        ),
                      ),
                    ],
                  ),
                ),
                _pad(
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (starts != null)
                        FactBox(
                          label: context.tr('When'),
                          value:
                              '${context.date(starts, pattern: 'EEEE, d MMMM y, HH:mm')}'
                              '${ends == null ? '' : ' – ${sameDay ? context.time(ends) : context.date(ends, pattern: 'd MMMM y, HH:mm')}'}',
                        ),
                      if (event.strOrNull('location') != null) ...[
                        const SizedBox(height: 14),
                        FactBox(
                          label: context.tr('Where'),
                          value: event.str('location'),
                        ),
                      ],
                      if (event.strOrNull('description') != null) ...[
                        const SizedBox(height: 28),
                        Text(
                          event.str('description'),
                          style: const TextStyle(
                            fontSize: 18,
                            height: 1.6,
                            color: Brand.charcoal,
                          ),
                        ),
                      ],
                      const SizedBox(height: 40),
                      _JoinPanel(
                        signInFirst: true,
                        title: context.tr('Want to take part?'),
                        text: context.tr(
                          'Sign in to register for this event. New students can join Tax Club for free.',
                        ),
                      ),
                    ],
                  ),
                  top: 32,
                ),
              ],
            );
          },
        ),
      ],
    );
  }
}

// ─── Questions and answers ─────────────────────────────────────────

class SiteAnswersScreen extends StatefulWidget {
  const SiteAnswersScreen({super.key});

  @override
  State<SiteAnswersScreen> createState() => _SiteAnswersScreenState();
}

class _SiteAnswersScreenState extends State<SiteAnswersScreen> {
  final _search = TextEditingController();
  String _query = '';
  int _page = 1;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'answers',
      children: [
        _HeroTitle(
          context.tr('Questions and answers'),
          context.tr(
            'Real questions from Tax Club members, answered by TRA tax experts.',
          ),
          extra: [
            const SizedBox(height: 28),
            ShadowSearch(
              controller: _search,
              hint: context.tr('Search answers, e.g. TIN or VAT'),
              onSearch: () => setState(() {
                _query = _search.text.trim();
                _page = 1;
              }),
            ),
          ],
        ),
        Loader<Paged<Map<String, dynamic>>>(
          key: ValueKey('$_query-$_page'),
          load: () => context.api.paged(
            'public/answers',
            (row) => row,
            query: {'search': _query, 'page': _page},
          ),
          builder: (context, page, _) => _pad(
            Column(
              children: [
                if (page.items.isEmpty)
                  MistBox(
                    context.tr(
                      'Answers will appear here as TRA publishes them.',
                    ),
                  ),
                for (final answer in page.items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: WebCard(
                      padding: const EdgeInsets.all(24),
                      onTap: () =>
                          context.push('/site/answers/view', extra: answer),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            answer.str('question'),
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              height: 1.3,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            answer.str('answer'),
                            maxLines: 4,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 17,
                              color: Brand.slate,
                              height: 1.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                _Pager(
                  page: page,
                  onPage: (value) => setState(() => _page = value),
                ),
                const SizedBox(height: 32),
                _JoinPanel(
                  signInFirst: true,
                  title: context.tr('Have a tax question?'),
                  text: context.tr(
                    'Tax Club members can ask TRA experts directly.',
                  ),
                ),
              ],
            ),
            top: 40,
          ),
        ),
      ],
    );
  }
}

class SiteAnswerScreen extends StatelessWidget {
  const SiteAnswerScreen({super.key, required this.answer});

  final Map<String, dynamic> answer;

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'answers',
      children: [
        Hatch(
          padding: const EdgeInsets.fromLTRB(16, 36, 16, 40),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LinkText(
                context.tr('Questions and answers'),
                onTap: () => context.go('/site/answers'),
              ),
              const SizedBox(height: 20),
              Text(
                answer.str('question'),
                style: const TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                ),
              ),
            ],
          ),
        ),
        _pad(
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.tr('Answer from TRA'),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: Brand.slate,
                ),
              ),
              const SizedBox(height: 8),
              SelectableText(
                answer.str('answer'),
                style: const TextStyle(
                  fontSize: 18,
                  height: 1.65,
                  color: Brand.charcoal,
                ),
              ),
              if (answer.date('published_at') != null) ...[
                const SizedBox(height: 16),
                Text(
                  '${context.tr('Published')} ${context.date(answer.date('published_at'), pattern: 'd MMMM y')}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: Brand.slate,
                  ),
                ),
              ],
              const SizedBox(height: 40),
              _JoinPanel(
                signInFirst: true,
                title: context.tr('Have a tax question?'),
                text: context.tr(
                  'Tax Club members can ask TRA experts directly.',
                ),
              ),
            ],
          ),
          top: 32,
        ),
      ],
    );
  }
}

// ─── About ─────────────────────────────────────────────────────────

class SiteAboutScreen extends StatelessWidget {
  const SiteAboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return PublicScaffold(
      current: 'about',
      children: [
        _HeroTitle(
          context.tr('About Tax Club'),
          context.tr(
            'Tax Clubs are student clubs supported by the Tanzania Revenue Authority. Members learn how taxes are collected and spent, why receipts matter, and how a fair tax system helps the country grow.',
          ),
        ),
        _pad(
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              BarHeading(context.tr('Who can join')),
              Bullets([
                context.tr(
                  'Students in primary and secondary schools, colleges and universities with a registered Tax Club.',
                ),
                context.tr(
                  'Former members, who continue as Tax Club alumni after they finish their studies.',
                ),
              ]),
              const SizedBox(height: 48),
              BarHeading(context.tr('What members do')),
              Bullets([
                context.tr(
                  'Take short lessons and quizzes on taxation, financial literacy and entrepreneurship.',
                ),
                context.tr(
                  'Take part in competitions, campaigns and events in their region.',
                ),
                context.tr(
                  'Ask TRA experts questions and find internships and other opportunities.',
                ),
              ]),
              const SizedBox(height: 48),
              BarHeading(context.tr('For patrons and schools')),
              const SizedBox(height: 14),
              Text(
                context.tr(
                  'Every Tax Club has a patron, usually a teacher or lecturer, who coordinates activities at the institution. Patrons confirm student registrations online and work with the TRA Tax Club coordinator for their region. To register your institution, contact the TRA office in your region.',
                ),
                style: const TextStyle(
                  fontSize: 18,
                  height: 1.6,
                  color: Brand.charcoal,
                ),
              ),
              const SizedBox(height: 48),
              BarHeading(context.tr('Keeping members safe')),
              const SizedBox(height: 14),
              Text(
                context.tr(
                  'Many members are under 18. Tax Club shows only limited information about members, never shares phone numbers publicly, and reviews community activity.',
                ),
                style: const TextStyle(
                  fontSize: 18,
                  height: 1.6,
                  color: Brand.charcoal,
                ),
              ),
            ],
          ),
          top: 48,
        ),
      ],
    );
  }
}
