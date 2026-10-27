import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/session.dart';
import '../../core/theme.dart';

/// Everything that is not a main tab.
class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final user = session.user;
    final photo = user.strOrNull('photo_url');

    Widget item(IconData icon, String label, String route, {String? subtitle}) => ListTile(
          leading: Icon(icon),
          title: Text(context.tr(label)),
          subtitle: subtitle == null ? null : Text(context.tr(subtitle)),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => context.push(route),
        );

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('More'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              contentPadding: const EdgeInsets.all(12),
              leading: CircleAvatar(
                radius: 26,
                backgroundColor: Brand.yellow,
                foregroundImage: photo == null ? null : NetworkImage(photo, headers: session.api.authHeaders),
                child: Text(user.str('full_name', '?').characters.first, style: const TextStyle(fontWeight: FontWeight.w700, color: Brand.ink)),
              ),
              title: Text(user.str('full_name'), style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(user.str('membership_id')),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/profile'),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(children: [
              item(Icons.badge_outlined, 'Membership card', '/card'),
              item(Icons.workspace_premium_outlined, 'My achievements', '/achievements', subtitle: 'Points, badges and certificates'),
              item(Icons.notifications_outlined, 'Notifications', '/notifications'),
            ]),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(children: [
              item(Icons.quiz_outlined, 'Quizzes', '/quizzes'),
              item(Icons.leaderboard_outlined, 'Leaderboard', '/leaderboard'),
              item(Icons.emoji_events_outlined, 'Competitions', '/competitions'),
              item(Icons.work_outline, 'Opportunities', '/opportunities', subtitle: 'Jobs, internships and scholarships'),
            ]),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(children: [
              item(Icons.help_outline, 'Ask TRA', '/ask', subtitle: 'Get answers from TRA experts'),
              item(Icons.handshake_outlined, 'Mentorship', '/mentorship'),
              if (!session.isMinor) item(Icons.groups_outlined, 'Alumni network', '/alumni'),
              item(Icons.feedback_outlined, 'Feedback', '/feedback'),
            ]),
          ),
          const SizedBox(height: 12),
          Card(child: item(Icons.settings_outlined, 'Settings', '/settings', subtitle: 'Language, password and notifications')),
        ],
      ),
    );
  }
}
