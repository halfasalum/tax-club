import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:tax_club/core/api.dart';
import 'package:tax_club/core/i18n.dart';
import 'package:tax_club/core/session.dart';
import 'package:tax_club/core/theme.dart';
import 'package:go_router/go_router.dart';
import 'package:tax_club/features/auth/auth_screens.dart';
import 'package:tax_club/features/notifications/notifications_screen.dart';

/// Answers API calls from a map of "METHOD path" to (status, body).
class FakeServer implements HttpClientAdapter {
  FakeServer(this.routes);

  final Map<String, (int, Object)> routes;
  final calls = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    calls.add(options);
    final route = routes['${options.method} ${options.path}'];
    if (route == null) {
      return ResponseBody.fromString(
        '{"message":"Not found."}',
        404,
        headers: _json,
      );
    }
    return ResponseBody.fromString(
      jsonEncode(route.$2),
      route.$1,
      headers: _json,
    );
  }

  static const _json = {
    Headers.contentTypeHeader: [Headers.jsonContentType],
  };

  @override
  void close({bool force = false}) {}
}

void main() {
  setUpAll(() async {
    await initializeDateFormatting('sw');
    await initializeDateFormatting('en');
  });

  group('I18n', () {
    setUp(
      () => I18n.use({
        'Hello, :name': 'Habari, :name',
        ':count lesson|:count lessons': 'somo :count|masomo :count',
      }),
    );

    test('translates to Kiswahili and fills placeholders', () {
      expect(
        I18n.translate('sw', 'Hello, :name', {'name': 'Asha'}),
        'Habari, Asha',
      );
      expect(
        I18n.translate('en', 'Hello, :name', {'name': 'Asha'}),
        'Hello, Asha',
      );
      expect(I18n.translate('sw', 'Not translated'), 'Not translated');
    });

    test('chooses singular or plural', () {
      expect(I18n.plural('sw', ':count lesson|:count lessons', 1), 'somo 1');
      expect(I18n.plural('sw', ':count lesson|:count lessons', 3), 'masomo 3');
      expect(I18n.plural('en', ':count lesson|:count lessons', 3), '3 lessons');
    });
  });

  test('JSON readers cope with loose types', () {
    final row = <String, dynamic>{
      'n': '5',
      'f': 1,
      'd': '2026-05-01T08:00:00Z',
      'list': [
        {'a': 1},
      ],
      'none': null,
    };
    expect(row.integer('n'), 5);
    expect(row.flag('f'), isTrue);
    expect(row.date('d')?.toUtc().hour, 8);
    expect(row.list('list').single['a'], 1);
    expect(row.strOrNull('none'), isNull);
    expect(row.str('missing', 'x'), 'x');
  });

  test('notification links open the matching app screen', () {
    expect(
      appRouteFor('https://club.flux.co.tz/app/events/tax-day-2026'),
      '/events/tax-day-2026',
    );
    expect(
      appRouteFor('https://club.flux.co.tz/en/app/feedback/FB-2026-00012'),
      '/feedback/FB-2026-00012',
    );
    expect(
      appRouteFor('https://club.flux.co.tz/app/quizzes/attempts/abc/result'),
      '/quizzes',
    );
    expect(appRouteFor('https://club.flux.co.tz/app'), '/home');
    expect(appRouteFor('https://example.org/news'), isNull);
  });

  group('Api', () {
    test(
      'reads data, and turns validation errors into field messages',
      () async {
        final api = Api(
          baseUrl: 'https://test',
          adapter: FakeServer({
            'GET quizzes': (
              200,
              {
                'success': true,
                'message': 'Success',
                'data': [
                  {'quiz_id': 'q1'},
                ],
              },
            ),
            'POST questions': (
              422,
              {
                'message': 'The subject field is required.',
                'errors': {
                  'subject': ['The subject field is required.'],
                },
              },
            ),
          }),
        );

        expect(listOf(await api.get('quizzes')).single['quiz_id'], 'q1');

        try {
          await api.post('questions', {});
          fail('expected an error');
        } on ApiException catch (error) {
          expect(error.statusCode, 422);
          expect(error.field('subject'), 'The subject field is required.');
        }
      },
    );

    test(
      'sends the token and language, and reports an expired session',
      () async {
        final server = FakeServer({
          'GET profile': (401, {'message': 'Unauthenticated.'}),
        });
        final api = Api(baseUrl: 'https://test', adapter: server)
          ..token = 'abc'
          ..locale = 'en';
        var expired = false;
        api.onUnauthorized = () => expired = true;

        await expectLater(api.get('profile'), throwsA(isA<ApiException>()));
        expect(server.calls.single.headers['Authorization'], 'Bearer abc');
        expect(server.calls.single.headers['Accept-Language'], 'en');
        expect(expired, isTrue);
      },
    );
  });

  group('Session', () {
    setUp(() => SharedPreferences.setMockInitialValues({'locale': 'en'}));

    test('signs in, keeps the user and signs out', () async {
      final server = FakeServer({
        'POST auth/login': (
          200,
          {
            'success': true,
            'data': {
              'token': 't1',
              'user': {'full_name': 'Asha Juma'},
              'permissions': [],
            },
          },
        ),
        'GET profile': (
          200,
          {
            'success': true,
            'data': {'full_name': 'Asha Juma', 'is_minor': true},
          },
        ),
        'POST auth/logout': (200, {'success': true}),
      });
      final session = Session(
        api: Api(baseUrl: 'https://test', adapter: server),
      );
      await session.start();
      expect(session.status, SessionStatus.signedOut);

      final result = await session.login('0712345678', 'secret123');
      expect(result.needsCode, isFalse);
      expect(session.signedIn, isTrue);
      expect(session.firstName, 'Asha');
      await session.refreshUser();
      expect(session.isMinor, isTrue);

      await session.logout();
      expect(session.signedIn, isFalse);
      expect(session.api.token, isNull);
    });

    test('staff accounts are asked for an SMS code', () async {
      final session = Session(
        api: Api(
          baseUrl: 'https://test',
          adapter: FakeServer({
            'POST auth/login': (
              200,
              {
                'success': true,
                'data': {
                  'two_factor_required': true,
                  'challenge': 'c1',
                  'expires_in_minutes': 10,
                },
              },
            ),
          }),
        ),
      );
      await session.start();
      final result = await session.login('officer@tra.go.tz', 'secret123');
      expect(result.needsCode, isTrue);
      expect(result.challenge, 'c1');
      expect(session.signedIn, isFalse);
    });
  });

  testWidgets('the sign-in page switches between Kiswahili and English', (
    tester,
  ) async {
    I18n.use({'Welcome back': 'Karibu tena', 'Sign in': 'Ingia'});
    SharedPreferences.setMockInitialValues({'locale': 'sw'});
    final session = Session(
      api: Api(baseUrl: 'https://test', adapter: FakeServer({})),
    );
    await tester.runAsync(session.start);
    final router = GoRouter(
      initialLocation: '/login',
      routes: [GoRoute(path: '/login', builder: (_, _) => const LoginScreen())],
    );

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: session,
        child: Consumer<Session>(
          builder: (context, session, _) => MaterialApp.router(
            theme: buildTheme(),
            routerConfig: router,
            locale: Locale(session.locale),
            supportedLocales: const [Locale('sw'), Locale('en')],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Karibu tena'), findsOneWidget);
    expect(find.text('Ingia'), findsWidgets);

    await tester.runAsync(() async {
      await tester.tap(find.text('English'));
      await Future<void>.delayed(const Duration(milliseconds: 50));
    });
    await tester.pumpAndSettle();
    expect(find.text('Welcome back'), findsOneWidget);
    expect(session.locale, 'en');
  });

  test('plural forms follow the website, including ranges', () {
    expect(
      I18n.plural(
        'en',
        '{0} You are all caught up.|{1} :count unread message|[2,*] :count unread messages',
        0,
      ),
      'You are all caught up.',
    );
    expect(
      I18n.plural(
        'en',
        '{0} You are all caught up.|{1} :count unread message|[2,*] :count unread messages',
        1,
      ),
      '1 unread message',
    );
    expect(
      I18n.plural(
        'en',
        '{0} You are all caught up.|{1} :count unread message|[2,*] :count unread messages',
        7,
      ),
      '7 unread messages',
    );
  });
}
