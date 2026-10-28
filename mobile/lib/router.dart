import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'core/session.dart';
import 'features/auth/auth_screens.dart';
import 'features/card/card_screen.dart';
import 'features/community/community_screens.dart';
import 'features/engage/achievements_screen.dart';
import 'features/engage/alumni_screens.dart';
import 'features/engage/ask_screens.dart';
import 'features/engage/competition_screens.dart';
import 'features/events/event_screens.dart';
import 'features/feedback/feedback_screens.dart';
import 'features/home/home_screen.dart';
import 'features/learning/learning_screens.dart';
import 'features/notifications/notifications_screen.dart';
import 'features/opportunities/opportunity_screens.dart';
import 'features/profile/profile_screens.dart';
import 'features/quiz/quiz_screens.dart';
import 'features/site/site_screens.dart';
import 'features/verify/verify_screen.dart';

/// Pages anyone can open: the public website, sign in and registration.
bool isPublicPath(String path) =>
    path == '/' ||
    path.startsWith('/site/') ||
    const {'/login', '/register', '/recover', '/verify'}.contains(path);

/// Sign-in pages a signed-in member is sent away from.
const _guestOnly = {'/', '/login', '/register', '/recover'};

GoRouter buildRouter(Session session) {
  GoRoute page(String path, Widget Function(GoRouterState state) build) =>
      GoRoute(
        path: path,
        pageBuilder: (_, state) =>
            MaterialPage(key: state.pageKey, child: build(state)),
      );

  return GoRouter(
    initialLocation: session.signedIn ? '/home' : '/',
    refreshListenable: session,
    redirect: (context, state) {
      if (session.status == SessionStatus.starting) return null;
      final path = state.matchedLocation;
      if (!session.signedIn && !isPublicPath(path)) {
        return session.expired ? '/login' : '/';
      }
      if (session.signedIn && _guestOnly.contains(path)) return '/home';
      return null;
    },
    routes: [
      // Public website
      page('/', (_) => const SiteHomeScreen()),
      page('/site/learn', (_) => const SiteLearnScreen()),
      page(
        '/site/learn/:slug',
        (state) => SiteLessonScreen(slug: state.pathParameters['slug']!),
      ),
      page('/site/events', (_) => const SiteEventsScreen()),
      page(
        '/site/events/:slug',
        (state) => SiteEventScreen(slug: state.pathParameters['slug']!),
      ),
      page('/site/answers', (_) => const SiteAnswersScreen()),
      page(
        '/site/answers/view',
        (state) => SiteAnswerScreen(
          answer: state.extra as Map<String, dynamic>? ?? const {},
        ),
      ),
      page('/site/about', (_) => const SiteAboutScreen()),
      page('/login', (_) => const LoginScreen()),
      page('/register', (_) => const RegisterScreen()),
      page('/recover', (_) => const RecoverScreen()),
      page('/verify', (_) => const VerifyScreen()),

      // Member area, in the order of the website's menu
      page('/home', (_) => const HomeScreen()),
      page('/learn', (_) => const LearningScreen()),
      page(
        '/learn/module/:id',
        (state) => ModuleScreen(id: state.pathParameters['id']!),
      ),
      page(
        '/lesson/:id',
        (state) => LessonScreen(id: state.pathParameters['id']!),
      ),
      page('/quizzes', (_) => const QuizzesScreen()),
      page('/quiz/:id', (state) => QuizScreen(id: state.pathParameters['id']!)),
      page(
        '/quiz/:id/attempt',
        (state) => AttemptScreen(
          quizId: state.pathParameters['id']!,
          attempt: state.extra as Map<String, dynamic>? ?? const {},
        ),
      ),
      page(
        '/quiz/:id/result',
        (state) => ResultScreen(
          quizId: state.pathParameters['id']!,
          result: state.extra as Map<String, dynamic>?,
        ),
      ),
      page(
        '/quiz/:id/result/:attempt',
        (state) => ResultScreen(
          quizId: state.pathParameters['id']!,
          attemptId: state.pathParameters['attempt'],
        ),
      ),
      page('/competitions', (_) => const CompetitionsScreen()),
      page(
        '/competitions/:slug',
        (state) => CompetitionScreen(slug: state.pathParameters['slug']!),
      ),
      page('/events', (_) => const EventsScreen()),
      page('/events/mine', (_) => const MyEventsScreen()),
      page(
        '/events/:id',
        (state) => EventScreen(id: state.pathParameters['id']!),
      ),
      page('/opportunities', (_) => const OpportunitiesScreen()),
      page(
        '/opportunities/:id',
        (state) => OpportunityScreen(id: state.pathParameters['id']!),
      ),
      page('/leaderboard', (_) => const LeaderboardScreen()),
      page('/community', (_) => const SpacesScreen()),
      page(
        '/community/post/:id',
        (state) => PostScreen(id: state.pathParameters['id']!),
      ),
      page(
        '/community/:slug',
        (state) => SpaceScreen(
          slug: state.pathParameters['slug']!,
          space: state.extra as Map<String, dynamic>?,
        ),
      ),
      page('/ask', (_) => const AskScreen()),
      page('/ask/new', (_) => const AskQuestionScreen()),
      page('/alumni', (_) => const AlumniDirectoryScreen()),
      page('/mentorship', (_) => const MentorshipScreen()),
      page('/mentorship/request', (_) => const MentorRequestScreen()),
      page(
        '/mentorship/:id/session',
        (state) => LogSessionScreen(id: state.pathParameters['id']!),
      ),
      page('/card', (_) => const CardScreen()),
      page('/achievements', (_) => const AchievementsScreen()),
      page('/notifications', (_) => const NotificationsScreen()),
      page('/profile', (_) => const ProfileScreen()),
      page('/settings', (_) => const ProfileScreen()),
      page('/feedback', (_) => const FeedbackListScreen()),
      page('/feedback/new', (_) => const NewFeedbackScreen()),
      page(
        '/feedback/:reference',
        (state) =>
            FeedbackScreen(reference: state.pathParameters['reference']!),
      ),
    ],
  );
}
