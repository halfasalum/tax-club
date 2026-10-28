import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/session.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// The website's sign-in layout (layouts/auth.blade.php): yellow header
/// with the brand and a language button, then a white card.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.title,
    this.subtitle,
    required this.children,
  });

  final String title;
  final String? subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final other = session.locale == 'sw' ? 'en' : 'sw';

    return Scaffold(
      backgroundColor: Brand.mist,
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          Hatch(
            padding: EdgeInsets.fromLTRB(
              16,
              MediaQuery.of(context).padding.top + 16,
              16,
              18,
            ),
            child: Row(
              children: [
                if (GoRouter.of(context).canPop())
                  Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: SquareButton(
                      icon: Icons.arrow_back,
                      tooltip: context.tr('Back'),
                      size: 40,
                      onTap: () => context.pop(),
                    ),
                  ),
                InkWell(onTap: () => context.go('/'), child: const BrandMark()),
                const Spacer(),
                Material(
                  color: Brand.ink,
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => session.setLocale(other),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                      child: Text(
                        other == 'en' ? 'English' : 'Kiswahili',
                        style: const TextStyle(
                          color: Brand.yellow,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 28, 16, 40),
            child: Container(
              padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
                border: Border.all(color: Brand.line, width: 2),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x14000000),
                    blurRadius: 30,
                    offset: Offset(0, 12),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w900,
                      height: 1.15,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      subtitle!,
                      style: const TextStyle(
                        fontSize: 18,
                        color: Brand.slate,
                        height: 1.5,
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  ...children,
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Label above the field, as in the website's forms.
class WebField extends StatefulWidget {
  const WebField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.help,
    this.error,
    this.optional = false,
    this.password = false,
    this.keyboard,
    this.autofill,
    this.maxLines = 1,
    this.onSubmitted,
    this.capitalization = TextCapitalization.none,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final String? help;
  final String? error;
  final bool optional;
  final bool password;
  final TextInputType? keyboard;
  final Iterable<String>? autofill;
  final int maxLines;
  final ValueChanged<String>? onSubmitted;
  final TextCapitalization capitalization;

  @override
  State<WebField> createState() => _WebFieldState();
}

class _WebFieldState extends State<WebField> {
  bool _hidden = true;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: widget.label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                if (widget.optional)
                  TextSpan(
                    text: ' (${context.tr('optional')})',
                    style: const TextStyle(color: Brand.slate, fontSize: 16),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: widget.controller,
            obscureText: widget.password && _hidden,
            keyboardType: widget.keyboard,
            autofillHints: widget.autofill,
            maxLines: widget.password ? 1 : widget.maxLines,
            minLines: widget.maxLines > 1 ? 3 : 1,
            textCapitalization: widget.capitalization,
            onSubmitted: widget.onSubmitted,
            style: const TextStyle(fontSize: 17),
            decoration: InputDecoration(
              hintText: widget.hint,
              errorText: widget.error,
              errorMaxLines: 3,
              suffixIcon: widget.password
                  ? TextButton(
                      onPressed: () => setState(() => _hidden = !_hidden),
                      child: Text(
                        context.tr(_hidden ? 'Show' : 'Hide'),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    )
                  : null,
            ),
          ),
          if (widget.help != null) ...[
            const SizedBox(height: 8),
            Text(
              widget.help!,
              style: const TextStyle(color: Brand.slate, fontSize: 15),
            ),
          ],
        ],
      ),
    );
  }
}

/// Small grey section label inside forms ("Your details").
class FormSection extends StatelessWidget {
  const FormSection(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 8, bottom: 18),
    child: Text(
      text,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w800,
        color: Brand.slate,
      ),
    ),
  );
}

class ErrorLine extends StatelessWidget {
  const ErrorLine(this.text, {super.key});

  final String? text;

  @override
  Widget build(BuildContext context) {
    if (text == null) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFEE4E2),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        text!,
        style: const TextStyle(
          color: Brand.danger,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

// ─── Sign in ───────────────────────────────────────────────────────

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
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
    if (_identifier.text.trim().isEmpty || _password.text.isEmpty) {
      setState(
        () => _error = context.tr(
          'Enter your phone number or email and your password.',
        ),
      );
      return;
    }
    setState(() => _error = null);
    try {
      final result = await context.session.login(
        _identifier.text,
        _password.text,
      );
      if (result.needsCode && mounted) {
        setState(() => _challenge = result.challenge);
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = errorText(context, error));
    }
  }

  Future<void> _verify() async {
    setState(() => _error = null);
    try {
      await context.session.verifyCode(_challenge!, _code.text);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = errorText(context, error));
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    if (_challenge != null) {
      return AuthScaffold(
        title: context.tr('Welcome back'),
        subtitle: context.tr(
          'Staff accounts need a second step. We sent a 6-digit code by SMS to the phone number on your account.',
        ),
        children: [
          ErrorLine(_error),
          WebField(
            label: context.tr('Code from the SMS'),
            controller: _code,
            keyboard: TextInputType.number,
            autofill: const [AutofillHints.oneTimeCode],
            onSubmitted: (_) => _verify(),
          ),
          WebButton(context.tr('Confirm and sign in'), onPressed: _verify),
          const SizedBox(height: 16),
          Center(
            child: LinkText(
              context.tr('Start again'),
              onTap: () => setState(() => _challenge = null),
            ),
          ),
        ],
      );
    }

    return AuthScaffold(
      title: context.tr('Welcome back'),
      subtitle: context.tr(
        'Use the phone number or email you registered with.',
      ),
      children: [
        if (session.expired)
          ErrorLine(context.tr('You were signed out. Please sign in again.')),
        ErrorLine(_error),
        WebField(
          label: context.tr('Phone number or email'),
          controller: _identifier,
          hint: '0712 345 678',
          keyboard: TextInputType.emailAddress,
          autofill: const [AutofillHints.telephoneNumber, AutofillHints.email],
        ),
        WebField(
          label: context.tr('Password'),
          controller: _password,
          password: true,
          autofill: const [AutofillHints.password],
          onSubmitted: (_) => _signIn(),
        ),
        Align(
          alignment: Alignment.centerRight,
          child: LinkText(
            context.tr('Forgot your password?'),
            onTap: () => context.push('/recover'),
          ),
        ),
        const SizedBox(height: 24),
        WebButton(context.tr('Sign in'), onPressed: _signIn),
        const SizedBox(height: 28),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Brand.yellowWash,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                context.tr('New to Tax Club?'),
                style: const TextStyle(
                  fontWeight: FontWeight.w900,
                  fontSize: 17,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.tr(
                  'Membership is free for students. It takes about two minutes.',
                ),
                style: const TextStyle(
                  color: Brand.slate,
                  fontSize: 16,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 14),
              WebButton(
                context.tr('Join Tax Club'),
                kind: BtnKind.brand,
                onPressed: () async => context.pushReplacement('/register'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Center(
          child: LinkText(
            context.tr('Check a membership card or certificate'),
            onTap: () => context.push('/verify'),
          ),
        ),
      ],
    );
  }
}

// ─── Recover ───────────────────────────────────────────────────────

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

  Future<void> _send() async {
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('auth/recover', {
        'identifier': _identifier.text.trim(),
      });
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
    return AuthScaffold(
      title: context.tr('Reset your password'),
      subtitle: _codeSent
          ? null
          : context.tr(
              'Enter the phone number or email you registered with. We will send a 6-digit code by SMS to your phone.',
            ),
      children: [
        if (_error != null && _error!.errors.isEmpty)
          ErrorLine(errorText(context, _error!)),
        if (!_codeSent) ...[
          WebField(
            label: context.tr('Phone number or email'),
            controller: _identifier,
            hint: '0712 345 678',
            keyboard: TextInputType.emailAddress,
            error: _error?.field('identifier'),
          ),
          WebButton(context.tr('Send code'), onPressed: _send),
        ] else ...[
          WebField(
            label: context.tr('Code from the SMS'),
            controller: _code,
            keyboard: TextInputType.number,
            autofill: const [AutofillHints.oneTimeCode],
            error: _error?.field('code'),
          ),
          WebField(
            label: context.tr('New password'),
            controller: _password,
            password: true,
            help: context.tr('At least 8 characters.'),
            error: _error?.field('password'),
          ),
          WebField(
            label: context.tr('Repeat new password'),
            controller: _confirm,
            password: true,
          ),
          WebButton(context.tr('Change password'), onPressed: _reset),
          const SizedBox(height: 14),
          Center(child: LinkText(context.tr('Send a new code'), onTap: _send)),
        ],
        const SizedBox(height: 24),
        LinkText(
          context.tr('Back to sign in'),
          onTap: () => context.pushReplacement('/login'),
        ),
      ],
    );
  }
}

// ─── Register ──────────────────────────────────────────────────────

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianPhone = TextEditingController();
  final _tin = TextEditingController();

  DateTime? _birthDate;
  String? _gender;
  String _userType = 'secondary';
  Map<String, dynamic>? _institution;
  String _relationship = 'parent';
  bool _consent = false;
  bool _agree = false;
  ApiException? _error;
  String? _reference;

  /// College and university students give their TIN.
  bool get _needsTin =>
      (context.session.config['tin_required_for'] as List? ??
              const ['college', 'university'])
          .contains(_userType);

  bool get _isMinor {
    if (_birthDate == null) return false;
    final now = DateTime.now();
    var age = now.year - _birthDate!.year;
    if (now.month < _birthDate!.month ||
        (now.month == _birthDate!.month && now.day < _birthDate!.day)) {
      age--;
    }
    return age < 18;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _phone,
      _email,
      _password,
      _confirm,
      _guardianName,
      _guardianPhone,
      _tin,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickBirthDate() async {
    final now = DateTime.now();
    final minAge = (context.session.config['min_age'] as num?)?.toInt() ?? 6;
    final picked = await showDatePicker(
      context: context,
      initialDate: _birthDate ?? DateTime(now.year - 16, 1, 1),
      firstDate: DateTime(now.year - 36),
      lastDate: DateTime(now.year - minAge, now.month, now.day),
      helpText: context.tr('Date of birth'),
    );
    if (picked != null) setState(() => _birthDate = picked);
  }

  Future<void> _pickInstitution() async {
    final picked = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _InstitutionPicker(type: _userType),
    );
    if (picked != null) setState(() => _institution = picked);
  }

  Future<void> _submit() async {
    if (!_agree) {
      showMessage(
        context,
        context.tr('Please agree so that TRA can manage your membership.'),
        error: true,
      );
      return;
    }
    setState(() => _error = null);
    try {
      final envelope = await context.api.post('auth/register', {
        'full_name': _name.text.trim(),
        'phone': _phone.text.trim(),
        if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
        if (_birthDate != null)
          'date_of_birth': _birthDate!.toIso8601String().substring(0, 10),
        if (_gender != null) 'gender': _gender,
        'user_type': _userType,
        if (_needsTin) 'tin': _tin.text.trim(),
        if (_institution != null)
          'institution_id': _institution!.str('institution_id'),
        if (_isMinor) ...{
          'guardian_name': _guardianName.text.trim(),
          'guardian_phone': _guardianPhone.text.trim(),
          'guardian_relationship': _relationship,
          'guardian_consent': _consent,
        },
        'password': _password.text,
        'password_confirmation': _confirm.text,
      });
      setState(
        () => _reference = dataOf(envelope).str('registration_reference'),
      );
    } on ApiException catch (error) {
      setState(() => _error = error);
      if (mounted) showMessage(context, errorText(context, error), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_reference != null) {
      return AuthScaffold(
        title: context.tr('Registration received'),
        subtitle: context.tr(
          'Your patron or TRA coordinator will confirm your membership. You can sign in once it is approved.',
        ),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Brand.yellowWash,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('Registration reference'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: Brand.slate,
                  ),
                ),
                const SizedBox(height: 4),
                SelectableText(
                  _reference!,
                  style: const TextStyle(
                    fontFamily: 'IBMPlexMono',
                    fontSize: 22,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  context.tr(
                    'Keep this reference. You may be asked for it when your membership is checked.',
                  ),
                  style: const TextStyle(color: Brand.slate),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          WebButton(
            context.tr('Go to sign in'),
            onPressed: () async => context.pushReplacement('/login'),
          ),
        ],
      );
    }

    return AuthScaffold(
      title: context.tr('Join Tax Club'),
      subtitle: context.tr(
        'Membership is free for students. It takes about two minutes.',
      ),
      children: [
        FormSection(context.tr('Your details')),
        WebField(
          label: context.tr('Full name'),
          controller: _name,
          capitalization: TextCapitalization.words,
          autofill: const [AutofillHints.name],
          error: _error?.field('full_name'),
        ),
        _label(context.tr('Date of birth')),
        InkWell(
          onTap: _pickBirthDate,
          borderRadius: BorderRadius.circular(12),
          child: InputDecorator(
            decoration: InputDecoration(
              suffixIcon: const Icon(Icons.calendar_today_outlined),
              errorText: _error?.field('date_of_birth'),
              errorMaxLines: 3,
            ),
            child: Text(
              _birthDate == null
                  ? context.tr('Choose a date')
                  : context.date(_birthDate),
              style: const TextStyle(fontSize: 17),
            ),
          ),
        ),
        const SizedBox(height: 20),
        _label(context.tr('Gender'), optional: true),
        Wrap(
          spacing: 8,
          children: [
            for (final option in {
              'female': 'Female',
              'male': 'Male',
              '': 'Prefer not to say',
            }.entries)
              ChoiceChip(
                label: Text(context.tr(option.value)),
                selected: (_gender ?? '') == option.key,
                onSelected: (_) => setState(
                  () => _gender = option.key.isEmpty ? null : option.key,
                ),
              ),
          ],
        ),
        const SizedBox(height: 20),
        WebField(
          label: context.tr('Mobile number'),
          controller: _phone,
          hint: '0712 345 678',
          keyboard: TextInputType.phone,
          autofill: const [AutofillHints.telephoneNumber],
          help: context.tr('You will use this number to sign in.'),
          error: _error?.field('phone'),
        ),
        WebField(
          label: context.tr('Email'),
          controller: _email,
          optional: true,
          keyboard: TextInputType.emailAddress,
          error: _error?.field('email'),
        ),
        FormSection(context.tr('Where do you study?')),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 2.6,
          children: [
            for (final option in optionsOf(context, 'user_types').entries)
              _ChoiceBox(
                label: option.value,
                selected: _userType == option.key,
                onTap: () => setState(() {
                  if (_userType != option.key) _institution = null;
                  _userType = option.key;
                }),
              ),
          ],
        ),
        const SizedBox(height: 20),
        if (_needsTin) ...[
          WebField(
            label: context.tr('Your TIN (Taxpayer Identification Number)'),
            controller: _tin,
            hint: '123-456-789',
            keyboard: TextInputType.number,
            error: _error?.field('tin'),
          ),
          Container(
            margin: const EdgeInsets.only(bottom: 20),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Brand.yellowWash,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr(
                    'College and university students need a TIN to join.',
                  ),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  context.tr(
                    'No TIN yet? Register for one free on the TRA website, then come back and finish joining.',
                  ),
                  style: const TextStyle(color: Brand.slate, height: 1.5),
                ),
                const SizedBox(height: 10),
                LinkText(
                  '${context.tr('Register for a TIN')} ↗',
                  onTap: () => openLink(context, tinRegistrationUrl(context)),
                ),
              ],
            ),
          ),
        ],
        _label(context.tr('Your institution')),
        InkWell(
          onTap: _pickInstitution,
          borderRadius: BorderRadius.circular(12),
          child: InputDecorator(
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.search),
              errorText: _error?.field('institution_id'),
              errorMaxLines: 3,
            ),
            child: Text(
              _institution == null
                  ? context.tr('Type at least 2 letters of the name')
                  : '${_institution!.str('name')} · ${_institution!.str('region')}',
              style: TextStyle(
                fontSize: 17,
                color: _institution == null ? Brand.slate : Brand.ink,
              ),
            ),
          ),
        ),
        const SizedBox(height: 20),
        if (_isMinor) ...[
          FormSection(context.tr('Parent or guardian')),
          Text(
            context.tr(
              'Because you are under 18, we need your parent or guardian’s details. Your patron will check that they agree before approving your membership.',
            ),
            style: const TextStyle(color: Brand.slate, height: 1.5),
          ),
          const SizedBox(height: 16),
          WebField(
            label: context.tr('Parent or guardian’s full name'),
            controller: _guardianName,
            capitalization: TextCapitalization.words,
            error: _error?.field('guardian_name'),
          ),
          WebField(
            label: context.tr('Their mobile number'),
            controller: _guardianPhone,
            hint: '0712 345 678',
            keyboard: TextInputType.phone,
            error: _error?.field('guardian_phone'),
          ),
          _label(context.tr('They are my')),
          Wrap(
            spacing: 8,
            children: [
              for (final option in optionsOf(
                context,
                'guardian_relationships',
              ).entries)
                ChoiceChip(
                  label: Text(option.value),
                  selected: _relationship == option.key,
                  onSelected: (_) => setState(() => _relationship = option.key),
                ),
            ],
          ),
          const SizedBox(height: 8),
          _Check(
            value: _consent,
            onChanged: (value) => setState(() => _consent = value),
            text: context.tr(
              'My parent or guardian knows about Tax Club and agrees that I can join.',
            ),
            error: _error?.field('guardian_consent'),
          ),
          const SizedBox(height: 12),
        ],
        FormSection(context.tr('Create a password')),
        WebField(
          label: context.tr('Password'),
          controller: _password,
          password: true,
          autofill: const [AutofillHints.newPassword],
          help: context.tr('At least 8 characters.'),
          error: _error?.field('password'),
        ),
        WebField(
          label: context.tr('Repeat password'),
          controller: _confirm,
          password: true,
        ),
        _Check(
          value: _agree,
          onChanged: (value) => setState(() => _agree = value),
          text: context.tr(
            'I agree that TRA may use my details to manage my Tax Club membership.',
          ),
        ),
        const SizedBox(height: 22),
        WebButton(
          context.tr('Submit registration'),
          kind: BtnKind.brand,
          onPressed: _submit,
        ),
        const SizedBox(height: 20),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              '${context.tr('Already a member?')} ',
              style: const TextStyle(color: Brand.slate, fontSize: 16),
            ),
            LinkText(
              context.tr('Sign in'),
              onTap: () => context.pushReplacement('/login'),
            ),
          ],
        ),
      ],
    );
  }

  Widget _label(String text, {bool optional = false}) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text.rich(
      TextSpan(
        children: [
          TextSpan(
            text: text,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
          if (optional)
            TextSpan(
              text: ' (${context.tr('optional')})',
              style: const TextStyle(color: Brand.slate),
            ),
        ],
      ),
    ),
  );
}

/// Bordered option box (the website's school-type buttons).
class _ChoiceBox extends StatelessWidget {
  const _ChoiceBox({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? Brand.yellow : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: selected ? Brand.ink : Brand.line, width: 2),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 60),
          alignment: Alignment.center,
          padding: const EdgeInsets.all(6),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
      ),
    );
  }
}

class _Check extends StatelessWidget {
  const _Check({
    required this.value,
    required this.onChanged,
    required this.text,
    this.error,
  });

  final bool value;
  final ValueChanged<bool> onChanged;
  final String text;
  final String? error;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(4, 8, 12, 8),
      decoration: BoxDecoration(
        color: Brand.mist,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Checkbox(value: value, onChanged: (v) => onChanged(v ?? false)),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Text(
                    text,
                    style: const TextStyle(fontSize: 16, height: 1.5),
                  ),
                ),
              ),
            ],
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(left: 12),
              child: Text(error!, style: const TextStyle(color: Brand.danger)),
            ),
        ],
      ),
    );
  }
}

class _InstitutionPicker extends StatefulWidget {
  const _InstitutionPicker({required this.type});

  final String type;

  @override
  State<_InstitutionPicker> createState() => _InstitutionPickerState();
}

class _InstitutionPickerState extends State<_InstitutionPicker> {
  final _list = GlobalKey<PagedListState<Map<String, dynamic>>>();
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.85,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: TextField(
              autofocus: true,
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                hintText: context.tr('Type at least 2 letters of the name'),
              ),
              onChanged: (value) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 400), () {
                  _search = value.trim();
                  _list.currentState?.refresh();
                });
              },
            ),
          ),
          Expanded(
            child: PagedList<Map<String, dynamic>>(
              key: _list,
              padding: const EdgeInsets.symmetric(horizontal: 8),
              separator: 0,
              load: (page) => context.api.paged(
                'institutions',
                (row) => row,
                query: {
                  'type': widget.type,
                  'search': _search,
                  'page': page,
                  'per_page': 30,
                },
              ),
              empty: EmptyPanel(
                message: context.tr(
                  'No registered institution matches. Ask your patron to register your institution with TRA.',
                ),
                icon: Icons.school_outlined,
              ),
              itemBuilder: (context, item, _) => ListTile(
                leading: const Icon(Icons.school_outlined),
                title: Text(
                  item.str('name'),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  [
                    item.str('district'),
                    item.str('region'),
                  ].where((part) => part.isNotEmpty).join(', '),
                ),
                onTap: () => Navigator.pop(context, item),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// TRA's online TIN registration, from the server or the known address.
String tinRegistrationUrl(BuildContext context) =>
    context.session.config.obj('links')?.strOrNull('tin_registration') ??
    'https://idras-ept.tra.go.tz/ept/reg/tpap/reg-tpap0101004';
