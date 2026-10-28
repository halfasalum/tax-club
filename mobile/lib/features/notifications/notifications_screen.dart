import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/theme.dart';
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
    final data = ((await context.api.get('notifications', query: {'page': page})) as Map).cast<String, dynamic>();
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
      context.api.post('notifications/$id/read').then((envelope) {
        if (mounted) context.session.setUnread(dataOf(envelope).integer('unread_count'));
      }).catchError((_) {});
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
    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Notifications')),
        actions: [
          TextButton(
            onPressed: () async {
              final result = await runAction(context, () => context.api.post('notifications/read-all'));
              if (result != null && context.mounted) {
                context.session.setUnread(0);
                _list.currentState?.refresh();
              }
            },
            child: Text(context.tr('Mark all read')),
          ),
        ],
      ),
      body: PagedList<Map<String, dynamic>>(
        key: _list,
        separator: 8,
        load: _load,
        empty: EmptyPanel(message: context.tr('No notifications yet.'), icon: Icons.notifications_none),
        itemBuilder: (context, notification, _) {
          final unread = notification.strOrNull('read_at') == null && !_read.contains(notification.str('id'));
          return Tile(
            color: unread ? Brand.yellowWash : null,
            onTap: () => _open(notification),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(unread ? Icons.circle : Icons.circle_outlined, size: 12, color: unread ? Brand.ink : Brand.line),
              const SizedBox(width: 10),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(notification.str('message'), style: TextStyle(fontWeight: unread ? FontWeight.w600 : FontWeight.w400)),
                  const SizedBox(height: 4),
                  Text(context.ago(notification.date('created_at')), style: const TextStyle(color: Brand.slate, fontSize: 12)),
                ]),
              ),
            ]),
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
    RegExp(r'^/(?:en/)?app/opportunities/([^/]+)'): (m) => '/opportunities/${m[1]}',
    RegExp(r'^/(?:en/)?app/quizzes/([0-9a-f-]{36})'): (m) => '/quiz/${m[1]}',
    RegExp(r'^/(?:en/)?app/community/posts/([^/]+)'): (m) => '/community/post/${m[1]}',
    RegExp(r'^/(?:en/)?app/feedback/([^/]+)'): (m) => '/feedback/${m[1]}',
    RegExp(r'^/(?:en/)?app/competitions/([^/]+)'): (m) => '/competitions/${m[1]}',
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
