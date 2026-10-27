import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/i18n.dart';
import '../../core/session.dart';
import '../../core/theme.dart';

class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    return Scaffold(
      backgroundColor: Brand.yellow,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight - 48),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Align(alignment: Alignment.topRight, child: LanguageSwitch(session: session)),
                  const SizedBox(height: 24),
                  Image.asset('assets/images/tra-logo.png', height: 130),
                  const SizedBox(height: 24),
                  Text(
                    'TRA Tax Club',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800, color: Brand.ink),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    context.tr('Learn about taxes, take quizzes, join events and grow with Tax Clubs across Tanzania.'),
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Brand.charcoal, height: 1.4),
                  ),
                  if (session.expired) ...[
                    const SizedBox(height: 16),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(context.tr('You were signed out. Please sign in again.'), textAlign: TextAlign.center),
                      ),
                    ),
                  ],
                  const SizedBox(height: 40),
                  FilledButton(onPressed: () => context.push('/login'), child: Text(context.tr('Sign in'))),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(backgroundColor: Colors.white),
                    onPressed: () => context.push('/register'),
                    child: Text(context.tr('Become a member')),
                  ),
                  const SizedBox(height: 24),
                  TextButton.icon(
                    onPressed: () => context.push('/verify'),
                    icon: const Icon(Icons.verified_outlined),
                    label: Text(context.tr('Check a membership card or certificate')),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Kiswahili / English toggle.
class LanguageSwitch extends StatelessWidget {
  const LanguageSwitch({super.key, required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return SegmentedButton<String>(
      showSelectedIcon: false,
      style: SegmentedButton.styleFrom(
        backgroundColor: Colors.white,
        selectedBackgroundColor: Brand.ink,
        selectedForegroundColor: Colors.white,
        visualDensity: VisualDensity.compact,
      ),
      segments: const [
        ButtonSegment(value: 'sw', label: Text('Kiswahili')),
        ButtonSegment(value: 'en', label: Text('English')),
      ],
      selected: {session.locale},
      onSelectionChanged: (value) => session.setLocale(value.first),
    );
  }
}
