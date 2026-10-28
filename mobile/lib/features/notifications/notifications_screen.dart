import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/session.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// The inbox. Links to the website are opened in the matching app screen
/// where one exists.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _list = GlobalKey<PagedListState<Map<String, dynamic>>>();
  final _read = <String>{};

  Future<Paged<Map<String, dynamic>>> _load(int page) async {
    final data = ((await context.api.get(
      'notifications',
      query: {'page': page},
    )) as Map).cast<String, dynamic>();
    if (mounted) context.session.setUnread(data.integer('unread_count'));
    final pagination = data.obj('pagination') ?? const {};
    return Paged(
      data.list('notifications'),
      currentPage: pagination.integer('current_page', 1),
      lastPage: pagination.integer('last_page', 1),
      total: pagination.integer('total'),
    );
  }

  Future<void> _open(Map<String, dynamic> notification) async {
    final id = notification.str('id');
    if (notification.strOrNull('read_at') == null && !_read.contains(id)) {
      setState(() => _read.add(id));
      context.api
          .post('notifications/$id/read')
          .then((envelope) {
            if (mounted) {
              context.session.setUnread(
                dataOf(envelope).integer('unread_count'),
              );
            }
          })
          .catchError((_) {});
    }

    final route = appRouteFor(notification.strOrNull('url'));
    if (route != null && tabRoots.contains(route)) {
      context.go(route);
    } else if (route != null) {
      context.push(route);
    } else if (notification.strOrNull('url') != null) {
      openLink(context, notification.str('url'));
    }
  }

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<Session>().unread;
    return MemberScaffold(
      body: PagedList<Map<String, dynamic>>(
        key: _list,
        padding: const EdgeInsets.fromLTRB(16, 28, 16, 40),
        separator: 10,
        load: _load,
        header: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PageTitle(
              context.tr('Notifications'),
              subtitle: context.trPlural(
                '{0} You are all caught up.|{1} :count unread message|[2,*] :count unread messages',
                unread,
              ),
            ),
            if (unread > 0) ...[
              const SizedBox(height: 14),
              WebButton(
                context.tr('Mark all as read'),
                kind: BtnKind.ghost,
                expand: false,
                onPressed: () async {
                  final result = await runAction(
                    context,
                    () => context.api.post('notifications/read-all'),
                  );
                  if (result != null && context.mounted) {
                    context.session.setUnread(0);
                    _list.currentState?.refresh();
                  }
                },
              ),
            ],
            const SizedBox(height: 12),
          ],
        ),
        empty: MistBox(
          context.tr(
            'No notifications yet. Updates about your membership will appear here.',
          ),
          color: Colors.white,
          center: true,
        ),
        itemBuilder: (context, notification, _) {
          final isUnread =
              notification.strOrNull('read_at') == null &&
              !_read.contains(notification.str('id'));
          return WebCard(
            highlight: isUnread,
            color: isUnread ? Brand.yellowWash : Colors.white,
            onTap: () => _open(notification),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (isUnread)
                  const Padding(
                    padding: EdgeInsets.only(top: 6, right: 10),
                    child: CircleAvatar(radius: 5, backgroundColor: Brand.ink),
                  ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        notification.str('message'),
                        style: TextStyle(
                          fontSize: 16,
                          height: 1.45,
                          fontWeight: isUnread
                              ? FontWeight.w800
                              : FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        context.ago(notification.date('created_at')),
                        style: const TextStyle(
                          color: Brand.slate,
                          fontSize: 13,
                        ),
                      ),
                    ],
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

const tabRoots = {'/home', '/learn', '/events', '/community', '/more'};

/// Maps a website link from a notification to a screen in the app.
String? appRouteFor(String? url) {
  if (url == null) return null;
  final path = Uri.tryParse(url)?.path ?? '';
  final patterns = <RegExp, String Function(Match)>{
    RegExp(r'^/(?:en/)?app/events/([^/]+)'): (m) => '/events/${m[1]}',
    RegExp(r'^/(?:en/)?app/opportunities/([^/]+)'): (m) =>
        '/opportunities/${m[1]}',
    RegExp(r'^/(?:en/)?app/quizzes/([0-9a-f-]{36})'): (m) => '/quiz/${m[1]}',
    RegExp(r'^/(?:en/)?app/community/posts/([^/]+)'): (m) =>
        '/community/post/${m[1]}',
    RegExp(r'^/(?:en/)?app/feedback/([^/]+)'): (m) => '/feedback/${m[1]}',
    RegExp(r'^/(?:en/)?app/competitions/([^/]+)'): (m) =>
        '/competitions/${m[1]}',
    RegExp(r'^/(?:en/)?app/quizzes'): (_) => '/quizzes',
    RegExp(r'^/(?:en/)?app/community/([^/]+)$'): (m) => '/community/${m[1]}',
    RegExp(r'^/(?:en/)?app/community'): (_) => '/community',
    RegExp(r'^/(?:en/)?app/events'): (_) => '/events',
    RegExp(r'^/(?:en/)?app/opportunities'): (_) => '/opportunities',
    RegExp(r'^/(?:en/)?app/feedback'): (_) => '/feedback',
    RegExp(r'^/(?:en/)?app/competitions'): (_) => '/competitions',
    RegExp(r'^/(?:en/)?app/points'): (_) => '/achievements',
    RegExp(r'^/(?:en/)?app/notifications'): (_) => '/notifications',
    RegExp(r'^/(?:en/)?app/alumni'): (_) => '/alumni',
    RegExp(r'^/(?:en/)?app/ask'): (_) => '/ask',
    RegExp(r'^/(?:en/)?app/achievements'): (_) => '/achievements',
    RegExp(r'^/(?:en/)?app/certificates'): (_) => '/achievements',
    RegExp(r'^/(?:en/)?app/mentorship'): (_) => '/mentorship',
    RegExp(r'^/(?:en/)?app/card'): (_) => '/card',
    RegExp(r'^/(?:en/)?app/learn'): (_) => '/learn',
    RegExp(r'^/(?:en/)?app/profile'): (_) => '/profile',
    RegExp(r'^/(?:en/)?app/?$'): (_) => '/home',
  };
  for (final entry in patterns.entries) {
    final match = entry.key.firstMatch(path);
    if (match != null) return entry.value(match);
  }
  return null;
}
