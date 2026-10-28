import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/session.dart';
import '../../core/shells.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// "My Tax Club" (livewire/member/dashboard.blade.php).
class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  Future<Map<String, dynamic>> _load(BuildContext context) async {
    final session = context.session;
    final results = await Future.wait([
      session.api.get('dashboard'),
      session.api.get('membership/card').catchError((_) => null),
    ]);
    final dashboard = (results[0] as Map).cast<String, dynamic>();
    session.setUnread(dashboard.integer('unread_notifications'));
    return {'dashboard': dashboard, 'card': results[1]};
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () => _load(context),
        builder: (context, data, reload) {
          final dashboard = data['dashboard'] as Map<String, dynamic>;
          final card = data['card'] is Map
              ? (data['card'] as Map).cast<String, dynamic>()
              : null;
          final lessons = dashboard.list('latest_lessons');
          final events = dashboard.list('events').isNotEmpty
              ? dashboard.list('events')
              : dashboard.list('upcoming_events');
          final firstName = session.firstName.isEmpty
              ? dashboard.str('full_name').split(' ').first
              : session.firstName;

          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                context.tr('Hello, :name', {'name': firstName}),
                subtitle: context.tr('Here is your Tax Club at a glance.'),
              ),
              const SizedBox(height: 28),
              MembershipCardView(
                name: card?.str('full_name') ?? dashboard.str('full_name'),
                number:
                    card?.str('membership_id') ??
                    dashboard.str('membership_id'),
                institution: card?.obj('institution')?.str('name'),
                category: labelOf(
                  context,
                  'user_types',
                  card?.strOrNull('user_type') ??
                      session.user.strOrNull('user_type'),
                ),
                memberSince: card?.date('member_since'),
                statusLabel:
                    card?.str('status_label') ?? context.tr('Active member'),
                valid: card?.flag('is_valid') ?? true,
              ),
              const SizedBox(height: 18),
              WebButton(
                context.tr('Open my card and QR code'),
                onPressed: () async => context.go('/card'),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: StatBox(
                      label: context.tr('Points'),
                      value: context.number(dashboard.integer('total_points')),
                      onTap: () => context.go('/leaderboard'),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: StatBox(
                      label: context.tr('Quizzes taken'),
                      value: context.number(dashboard.integer('quiz_attempts')),
                      onTap: () => context.go('/quizzes'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 40),
              SectionHeading(
                context.tr('Continue learning'),
                link: context.tr('All lessons'),
                onLink: () => context.go('/learn'),
              ),
              const SizedBox(height: 16),
              if (lessons.isEmpty)
                MistBox(
                  context.tr(
                    'Lessons will appear here as soon as TRA publishes them.',
                  ),
                  color: Colors.white,
                )
              else
                for (final lesson in lessons)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: WebCard(
                      onTap: () =>
                          context.push('/lesson/${lesson.str('content_id')}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Tag(
                            lesson.strOrNull('content_type_label') ??
                                labelOf(
                                  context,
                                  'content_types',
                                  lesson.str('content_type'),
                                ),
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
                          const SizedBox(height: 12),
                          LinkText(
                            context.tr('Open'),
                            onTap: () => context.push(
                              '/lesson/${lesson.str('content_id')}',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              const SizedBox(height: 32),
              SectionHeading(
                context.tr('Upcoming events'),
                link: context.tr('All events'),
                onLink: () => context.go('/events'),
              ),
              const SizedBox(height: 16),
              if (events.isEmpty)
                MistBox(
                  context.tr(
                    'No events are scheduled yet. We will notify you when one is announced.',
                  ),
                  color: Colors.white,
                )
              else
                for (final event in events)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: EventRowView(
                      title: event.str('title'),
                      startsAt: event.date('starts_at'),
                      location: event.strOrNull('location'),
                      onTap: () =>
                          context.push('/events/${event.str('event_id')}'),
                    ),
                  ),
            ],
          );
        },
      ),
    );
  }
}
