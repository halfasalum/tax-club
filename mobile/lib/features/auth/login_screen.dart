import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
  bool _hidePassword = true;
  String? _challenge;
  String? _error;

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _error = null);

    try {
      final result = await context.session.login(_identifier.text, _password.text);
      if (result.needsCode && mounted) setState(() => _challenge = result.challenge);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = errorText(context, error));
    }
  }

  Future<void> _verify() async {
    if (_code.text.trim().length != 6) {
      setState(() => _error = context.tr('Enter the 6-digit code.'));
      return;
    }
    setState(() => _error = null);

    try {
      await context.session.verifyCode(_challenge!, _code.text);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = errorText(context, error));
    }
  }

  @override
  Widget build(BuildContext context) {
    final needsCode = _challenge != null;

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Sign in'))),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              if (!needsCode) ...[
                Text(context.tr('Use the phone number or email you registered with.'), style: Theme.of(context).textTheme.bodyLarge),
                const SizedBox(height: 20),
                TextFormField(
                  controller: _identifier,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.telephoneNumber, AutofillHints.email],
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: context.tr('Phone number or email'), hintText: '0712 345 678', prefixIcon: const Icon(Icons.person_outline)),
                  validator: (value) => (value ?? '').trim().isEmpty ? context.tr('Enter your phone number or email.') : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _password,
                  obscureText: _hidePassword,
                  autofillHints: const [AutofillHints.password],
                  onFieldSubmitted: (_) => _signIn(),
                  decoration: InputDecoration(
                    labelText: context.tr('Password'),
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      tooltip: context.tr(_hidePassword ? 'Show password' : 'Hide password'),
                      icon: Icon(_hidePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                      onPressed: () => setState(() => _hidePassword = !_hidePassword),
                    ),
                  ),
                  validator: (value) => (value ?? '').isEmpty ? context.tr('Enter your password.') : null,
                ),
              ] else ...[
                Text(context.tr('We sent a 6-digit code by SMS to your phone. Enter it to finish signing in.'), style: Theme.of(context).textTheme.bodyLarge),
                const SizedBox(height: 20),
                TextField(
                  controller: _code,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  autofocus: true,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  decoration: InputDecoration(labelText: context.tr('SMS code'), prefixIcon: const Icon(Icons.sms_outlined)),
                  onSubmitted: (_) => _verify(),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error, fontWeight: FontWeight.w600)),
              ],
              const SizedBox(height: 24),
              BusyButton(label: context.tr(needsCode ? 'Confirm' : 'Sign in'), onPressed: needsCode ? _verify : _signIn),
              const SizedBox(height: 12),
              if (needsCode)
                TextButton(onPressed: () => setState(() => _challenge = null), child: Text(context.tr('Start again')))
              else ...[
                TextButton(onPressed: () => context.push('/recover'), child: Text(context.tr('Forgot your password?'))),
                TextButton(onPressed: () => context.pushReplacement('/register'), child: Text(context.tr('Not a member yet? Register'))),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
