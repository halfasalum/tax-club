import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'core/i18n.dart';
import 'core/session.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/recover_screen.dart';
import 'features/auth/register_screen.dart';
import 'features/auth/welcome_screen.dart';
import 'features/card/card_screen.dart';
import 'features/community/community_screens.dart';
import 'features/engage/ask_screens.dart';
import 'features/engage/competition_screens.dart';
import 'features/engage/achievements_screen.dart';
import 'features/engage/alumni_screens.dart';
import 'features/events/event_screens.dart';
import 'features/feedback/feedback_screens.dart';
import 'features/home/home_screen.dart';
import 'features/learning/learning_screens.dart';
import 'features/more/more_screen.dart';
import 'features/notifications/notifications_screen.dart';
import 'features/opportunities/opportunity_screens.dart';
import 'features/profile/profile_screens.dart';
import 'features/quiz/quiz_screens.dart';
import 'features/verify/verify_screen.dart';

/// Screens anyone can open without signing in.
const _public = {'/welcome', '/login', '/register', '/recover', '/verify'};

GoRouter buildRouter(Session session) {
  final rootKey = GlobalKey<NavigatorState>();

  GoRoute page(String path, Widget Function(GoRouterState state) build) =>
      GoRoute(path: path, parentNavigatorKey: rootKey, builder: (_, state) => build(state));

  return GoRouter(
    navigatorKey: rootKey,
    initialLocation: '/home',
    refreshListenable: session,
    redirect: (context, state) {
      if (session.status == SessionStatus.starting) return null;
      final path = state.matchedLocation;
      final isPublic = _public.contains(path);
      if (!session.signedIn && !isPublic) return '/welcome';
      if (session.signedIn && isPublic && path != '/verify') return '/home';
      return null;
    },
    routes: [
      page('/welcome', (_) => const WelcomeScreen()),
      page('/login', (_) => const LoginScreen()),
      page('/register', (_) => const RegisterScreen()),
      page('/recover', (_) => const RecoverScreen()),
      page('/verify', (_) => const VerifyScreen()),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => _Shell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/learn', builder: (_, _) => const LearningScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/events', builder: (_, _) => const EventsScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/community', builder: (_, _) => const SpacesScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/more', builder: (_, _) => const MoreScreen())]),
        ],
      ),
      page('/notifications', (_) => const NotificationsScreen()),
      page('/card', (_) => const CardScreen()),
      page('/learn/module/:id', (state) => ModuleScreen(id: state.pathParameters['id']!)),
      page('/lesson/:id', (state) => LessonScreen(id: state.pathParameters['id']!)),
      page('/quizzes', (_) => const QuizzesScreen()),
      page('/quiz/:id', (state) => QuizScreen(id: state.pathParameters['id']!)),
      page('/quiz/:id/attempt', (state) => AttemptScreen(quizId: state.pathParameters['id']!, attempt: state.extra as Map<String, dynamic>? ?? const {})),
      page('/quiz/:id/result', (state) => ResultScreen(quizId: state.pathParameters['id']!, result: state.extra as Map<String, dynamic>?)),
      page('/quiz/:id/result/:attempt', (state) => ResultScreen(quizId: state.pathParameters['id']!, attemptId: state.pathParameters['attempt'])),
      page('/quiz/:id/attempts', (state) => AttemptsScreen(quizId: state.pathParameters['id']!)),
      page('/leaderboard', (_) => const LeaderboardScreen()),
      page('/events/mine', (_) => const MyEventsScreen()),
      page('/events/:id', (state) => EventScreen(id: state.pathParameters['id']!)),
      page('/opportunities', (_) => const OpportunitiesScreen()),
      page('/opportunities/:id', (state) => OpportunityScreen(id: state.pathParameters['id']!)),
      page('/community/post/:id', (state) => PostScreen(id: state.pathParameters['id']!)),
      page('/community/:slug', (state) => SpaceScreen(slug: state.pathParameters['slug']!, name: state.extra as String?)),
      page('/ask', (_) => const AskScreen()),
      page('/ask/new', (_) => const AskQuestionScreen()),
      page('/competitions', (_) => const CompetitionsScreen()),
      page('/competitions/:slug', (state) => CompetitionScreen(slug: state.pathParameters['slug']!)),
      page('/achievements', (_) => const AchievementsScreen()),
      page('/alumni', (_) => const AlumniDirectoryScreen()),
      page('/mentorship', (_) => const MentorshipScreen()),
      page('/mentorship/request', (_) => const MentorRequestScreen()),
      page('/mentorship/:id/session', (state) => LogSessionScreen(id: state.pathParameters['id']!)),
      page('/feedback', (_) => const FeedbackListScreen()),
      page('/feedback/new', (_) => const NewFeedbackScreen()),
      page('/feedback/:reference', (state) => FeedbackScreen(reference: state.pathParameters['reference']!)),
      page('/profile', (_) => const ProfileScreen()),
      page('/settings', (_) => const SettingsScreen()),
      page('/settings/password', (_) => const ChangePasswordScreen()),
      page('/settings/phone', (_) => const ChangePhoneScreen()),
      page('/settings/notifications', (_) => const NotificationSettingsScreen()),
    ],
  );
}

class _Shell extends StatelessWidget {
  const _Shell({required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: (index) => shell.goBranch(index, initialLocation: index == shell.currentIndex),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home), label: context.tr('Home')),
          NavigationDestination(icon: const Icon(Icons.menu_book_outlined), selectedIcon: const Icon(Icons.menu_book), label: context.tr('Learn')),
          NavigationDestination(icon: const Icon(Icons.event_outlined), selectedIcon: const Icon(Icons.event), label: context.tr('Events')),
          NavigationDestination(icon: const Icon(Icons.forum_outlined), selectedIcon: const Icon(Icons.forum), label: context.tr('Community')),
          NavigationDestination(icon: const Icon(Icons.grid_view_outlined), selectedIcon: const Icon(Icons.grid_view), label: context.tr('More')),
        ],
      ),
    );
  }
}
