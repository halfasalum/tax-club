import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/widgets.dart';

/// Reset a forgotten password with a code sent by SMS.
class RecoverScreen extends StatefulWidget {
  const RecoverScreen({super.key});

  @override
  State<RecoverScreen> createState() => _RecoverScreenState();
}

class _RecoverScreenState extends State<RecoverScreen> {
  final _identifier = TextEditingController();
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _codeSent = false;
  ApiException? _error;

  @override
  void dispose() {
    for (final controller in [_identifier, _code, _password, _confirm]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _sendCode() async {
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('auth/recover', {'identifier': _identifier.text.trim()});
      if (!mounted) return;
      setState(() => _codeSent = true);
      showMessage(context, '${envelope['message']}');
    } on ApiException catch (error) {
      setState(() => _error = error);
    }
  }

  Future<void> _reset() async {
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('auth/recover/reset', {
        'identifier': _identifier.text.trim(),
        'code': _code.text.trim(),
        'password': _password.text,
        'password_confirmation': _confirm.text,
      });
      if (!mounted) return;
      showMessage(context, '${envelope['message']}');
      context.pushReplacement('/login');
    } on ApiException catch (error) {
      setState(() => _error = error);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Reset your password'))),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              context.tr(_codeSent
                  ? 'Enter the code we sent by SMS and choose a new password.'
                  : 'Enter your phone number or email. We will send a reset code by SMS to the phone on your account.'),
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 20),
            TextField(
              controller: _identifier,
              enabled: !_codeSent,
              keyboardType: TextInputType.emailAddress,
              decoration: InputDecoration(labelText: context.tr('Phone number or email'), errorText: _error?.field('identifier')),
            ),
            if (_codeSent) ...[
              const SizedBox(height: 16),
              TextField(
                controller: _code,
                keyboardType: TextInputType.number,
                maxLength: 6,
                autofillHints: const [AutofillHints.oneTimeCode],
                decoration: InputDecoration(labelText: context.tr('SMS code'), errorText: _error?.field('code')),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _password,
                obscureText: true,
                autofillHints: const [AutofillHints.newPassword],
                decoration: InputDecoration(labelText: context.tr('New password'), helperText: context.tr('At least 8 characters.'), errorText: _error?.field('password')),
              ),
              const SizedBox(height: 16),
              TextField(controller: _confirm, obscureText: true, decoration: InputDecoration(labelText: context.tr('Confirm new password'))),
            ],
            if (_error != null && _error!.errors.isEmpty) ...[
              const SizedBox(height: 12),
              Text(errorText(context, _error!), style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 24),
            BusyButton(label: context.tr(_codeSent ? 'Change password' : 'Send code'), onPressed: _codeSent ? _reset : _sendCode),
            if (_codeSent) TextButton(onPressed: _sendCode, child: Text(context.tr('Send the code again'))),
          ],
        ),
      ),
    );
  }
}
