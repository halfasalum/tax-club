import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  Future<Map<String, dynamic>> _load(BuildContext context) async {
    final session = context.session;
    final results = await Future.wait([
      session.api.get('dashboard'),
      session.api.get('learning').catchError((_) => null),
    ]);
    final dashboard = (results[0] as Map).cast<String, dynamic>();
    session.setUnread(dashboard.integer('unread_notifications'));
    return {'dashboard': dashboard, 'learning': results[1]};
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    return Scaffold(
      appBar: AppBar(
        title: Row(children: [
          Image.asset('assets/images/tra-logo.png', height: 30),
          const SizedBox(width: 10),
          const Text('Tax Club'),
        ]),
        actions: [
          IconButton(
            tooltip: context.tr('Notifications'),
            onPressed: () => context.push('/notifications'),
            icon: Badge(
              isLabelVisible: session.unread > 0,
              label: Text('${session.unread}'),
              child: const Icon(Icons.notifications_outlined),
            ),
          ),
          IconButton(tooltip: context.tr('Membership card'), onPressed: () => context.push('/card'), icon: const Icon(Icons.badge_outlined)),
        ],
      ),
      body: Loader<Map<String, dynamic>>(
        load: () => _load(context),
        builder: (context, data, reload) {
          final dashboard = data['dashboard'] as Map<String, dynamic>;
          final learning = data['learning'] is Map ? (data['learning'] as Map).cast<String, dynamic>() : null;
          final resume = learning?.obj('resume');
          final modules = learning?.list('modules') ?? const [];
          final progress = dashboard.obj('progress') ?? const {};

          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  context.tr('Hello, :name', {'name': session.firstName.isEmpty ? dashboard.str('display_name') : session.firstName}),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(dashboard.str('membership_id'), style: const TextStyle(color: Brand.slate)),
                const SizedBox(height: 16),
                Row(children: [
                  _Stat(icon: Icons.star_rounded, value: context.number(dashboard.integer('total_points')), label: context.tr('Points'), onTap: () => context.push('/achievements')),
                  const SizedBox(width: 10),
                  _Stat(icon: Icons.menu_book_rounded, value: '${progress['completed'] ?? 0}', label: context.tr('Lessons done'), onTap: () => context.go('/learn')),
                  const SizedBox(width: 10),
                  _Stat(icon: Icons.quiz_rounded, value: '${dashboard.integer('quiz_attempts')}', label: context.tr('Quizzes taken'), onTap: () => context.push('/quizzes')),
                ]),
                const SizedBox(height: 16),
                if (resume != null)
                  Tile(
                    color: Brand.yellow,
                    onTap: () => context.push('/lesson/${resume.str('content_id')}'),
                    child: Row(children: [
                      const Icon(Icons.play_circle_fill, size: 40, color: Brand.ink),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(context.tr('Continue learning'), style: const TextStyle(fontWeight: FontWeight.w600)),
                          Text(resume.str('title'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                        ]),
                      ),
                      const Icon(Icons.chevron_right),
                    ]),
                  )
                else if (modules.isNotEmpty)
                  Tile(
                    color: Brand.yellow,
                    onTap: () => context.go('/learn'),
                    child: Row(children: [
                      const Icon(Icons.school, size: 40, color: Brand.ink),
                      const SizedBox(width: 12),
                      Expanded(child: Text(context.tr('Start a learning module'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700))),
                      const Icon(Icons.chevron_right),
                    ]),
                  ),
                const SizedBox(height: 8),
                SectionTitle(context.tr('Quick links')),
                GridView.count(
                  crossAxisCount: 4,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 8,
                  crossAxisSpacing: 8,
                  childAspectRatio: 0.85,
                  children: [
                    _Shortcut(icon: Icons.quiz_outlined, label: context.tr('Quizzes'), route: '/quizzes'),
                    _Shortcut(icon: Icons.work_outline, label: context.tr('Opportunities'), route: '/opportunities'),
                    _Shortcut(icon: Icons.help_outline, label: context.tr('Ask TRA'), route: '/ask'),
                    _Shortcut(icon: Icons.emoji_events_outlined, label: context.tr('Competitions'), route: '/competitions'),
                    _Shortcut(icon: Icons.leaderboard_outlined, label: context.tr('Leaderboard'), route: '/leaderboard'),
                    _Shortcut(icon: Icons.workspace_premium_outlined, label: context.tr('Achievements'), route: '/achievements'),
                    _Shortcut(icon: Icons.handshake_outlined, label: context.tr('Mentorship'), route: '/mentorship'),
                    _Shortcut(icon: Icons.feedback_outlined, label: context.tr('Feedback'), route: '/feedback'),
                  ],
                ),
                const SizedBox(height: 8),
                SectionTitle(context.tr('My upcoming events'), action: context.tr('All events'), onAction: () => context.go('/events')),
                if (dashboard.list('upcoming_events').isEmpty)
                  Tile(onTap: () => context.go('/events'), child: Text(context.tr('You have not registered for any upcoming event. See what is on.')))
                else
                  for (final event in dashboard.list('upcoming_events'))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Tile(
                        onTap: () => context.push('/events/${event.str('event_id')}'),
                        child: Row(children: [
                          DateBox(date: event.date('starts_at')),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(event.str('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                              InfoRow(icon: Icons.place_outlined, text: event.str('location')),
                            ]),
                          ),
                        ]),
                      ),
                    ),
                const SizedBox(height: 8),
                SectionTitle(context.tr('New opportunities'), action: context.tr('See all'), onAction: () => context.push('/opportunities')),
                if (dashboard.list('open_opportunities').isEmpty)
                  Tile(child: Text(context.tr('No open opportunities right now.')))
                else
                  for (final opportunity in dashboard.list('open_opportunities'))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Tile(
                        onTap: () => context.push('/opportunities/${opportunity.str('opportunity_id')}'),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(opportunity.str('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                          InfoRow(icon: Icons.business_outlined, text: opportunity.str('organization')),
                          if (opportunity.date('deadline') != null)
                            InfoRow(icon: Icons.event_outlined, text: context.tr('Closes :date', {'date': context.date(opportunity.date('deadline'))})),
                        ]),
                      ),
                    ),
                if (dashboard.list('recent_quiz_attempts').isNotEmpty) ...[
                  const SizedBox(height: 8),
                  SectionTitle(context.tr('Recent quiz results')),
                  Card(
                    child: Column(children: [
                      for (final attempt in dashboard.list('recent_quiz_attempts'))
                        ListTile(
                          title: Text(attempt.str('quiz')),
                          subtitle: Text(context.date(attempt.date('attempted_at'))),
                          trailing: Text('${attempt.number('score_percentage').round()}%', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                        ),
                    ]),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.icon, required this.value, required this.label, this.onTap});

  final IconData icon;
  final String value;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Tile(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        child: Column(children: [
          Icon(icon, color: Brand.ink),
          const SizedBox(height: 6),
          Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          Text(label, textAlign: TextAlign.center, maxLines: 2, style: const TextStyle(fontSize: 12, color: Brand.slate)),
        ]),
      ),
    );
  }
}

class _Shortcut extends StatelessWidget {
  const _Shortcut({required this.icon, required this.label, required this.route});

  final IconData icon;
  final String label;
  final String route;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: () => context.push(route),
      child: Column(children: [
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: Brand.line)),
          child: Icon(icon, color: Brand.ink),
        ),
        const SizedBox(height: 6),
        Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
      ]),
    );
  }
}

/// The date badge used in event lists.
class DateBox extends StatelessWidget {
  const DateBox({super.key, required this.date});

  final DateTime? date;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 54,
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: BoxDecoration(color: Brand.yellow, borderRadius: BorderRadius.circular(10)),
      child: Column(children: [
        Text(context.date(date, pattern: 'MMM').toUpperCase(), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
        Text(context.date(date, pattern: 'd'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
      ]),
    );
  }
}

