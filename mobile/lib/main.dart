import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/i18n.dart';
import 'core/session.dart';
import 'core/theme.dart';
import 'router.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await I18n.load();
  final session = Session();
  await session.start();
  runApp(TaxClubApp(session: session));
}

class TaxClubApp extends StatefulWidget {
  const TaxClubApp({super.key, required this.session});

  final Session session;

  @override
  State<TaxClubApp> createState() => _TaxClubAppState();
}

class _TaxClubAppState extends State<TaxClubApp> {
  late final _router = buildRouter(widget.session);

  @override
  void dispose() {
    _router.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider.value(
      value: widget.session,
      child: Consumer<Session>(
        builder: (context, session, _) => MaterialApp.router(
          title: 'TRA Tax Club',
          debugShowCheckedModeBanner: false,
          theme: buildTheme(),
          routerConfig: _router,
          locale: Locale(session.locale),
          supportedLocales: const [Locale('sw'), Locale('en')],
          localizationsDelegates: const [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
        ),
      ),
    );
  }
}
