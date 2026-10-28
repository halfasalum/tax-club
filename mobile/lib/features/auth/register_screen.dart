import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// Membership registration. The account is confirmed by the member's
/// patron or a TRA coordinator before they can sign in.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _forms = List.generate(4, (_) => GlobalKey<FormState>());
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianPhone = TextEditingController();

  int _step = 0;
  DateTime? _birthDate;
  String? _gender;
  String _userType = 'secondary';
  Map<String, dynamic>? _institution;
  String _relationship = 'parent';
  bool _consent = false;
  bool _agree = false;
  ApiException? _error;
  String? _reference;

  static final _phonePattern = RegExp(r'^(\+?255|0)[67]\d{8}$');

  bool get _isMinor {
    if (_birthDate == null) return false;
    final now = DateTime.now();
    var age = now.year - _birthDate!.year;
    if (now.month < _birthDate!.month || (now.month == _birthDate!.month && now.day < _birthDate!.day)) age--;
    return age < 18;
  }

  List<int> get _steps => [0, 1, if (_isMinor) 2, 3];

  @override
  void dispose() {
    for (final controller in [_name, _phone, _email, _password, _confirm, _guardianName, _guardianPhone]) {
      controller.dispose();
    }
    super.dispose();
  }

  String? _phoneError(String? value) {
    final compact = (value ?? '').replaceAll(RegExp(r'[\s-]'), '');
    return _phonePattern.hasMatch(compact) ? null : context.tr('Enter a Tanzanian mobile number, for example 0712 345 678.');
  }

  Future<void> _pickBirthDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _birthDate ?? DateTime(now.year - 16, 1, 1),
      firstDate: DateTime(now.year - 36),
      lastDate: DateTime(now.year - 11, now.month, now.day),
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

  void _next() {
    final index = _steps.indexOf(_step);
    if (!_forms[_step].currentState!.validate()) return;

    if (_step == 0 && _birthDate == null) {
      showMessage(context, context.tr('Choose your date of birth.'), error: true);
      return;
    }
    if (_step == 1 && _institution == null) {
      showMessage(context, context.tr('Choose your school, college or university.'), error: true);
      return;
    }
    if (_step == 2 && !_consent) {
      showMessage(context, context.tr('Your parent or guardian must agree before you join.'), error: true);
      return;
    }

    setState(() => _step = _steps[index + 1]);
  }

  void _back() {
    final index = _steps.indexOf(_step);
    if (index == 0) {
      context.pop();
    } else {
      setState(() => _step = _steps[index - 1]);
    }
  }

  Future<void> _submit() async {
    if (!_forms[3].currentState!.validate()) return;
    if (!_agree) {
      showMessage(context, context.tr('Please agree to the Tax Club rules.'), error: true);
      return;
    }
    setState(() => _error = null);

    try {
      final envelope = await context.api.post('auth/register', {
        'full_name': _name.text.trim(),
        'phone': _phone.text.trim(),
        if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
        'date_of_birth': _birthDate!.toIso8601String().substring(0, 10),
        if (_gender != null) 'gender': _gender,
        'user_type': _userType,
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
      setState(() => _reference = dataOf(envelope).str('registration_reference'));
    } on ApiException catch (error) {
      setState(() {
        _error = error;
        // Send the member back to the step with the first problem.
        const stepOf = {
          'full_name': 0, 'phone': 0, 'email': 0, 'date_of_birth': 0, 'gender': 0,
          'user_type': 1, 'institution_id': 1,
          'guardian_name': 2, 'guardian_phone': 2, 'guardian_relationship': 2, 'guardian_consent': 2,
        };
        final first = error.errors.keys.map((key) => stepOf[key]).whereType<int>().fold<int?>(null, (a, b) => a == null || b < a ? b : a);
        if (first != null) _step = first;
      });
      if (mounted) showMessage(context, errorText(context, error), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_reference != null) return _Submitted(reference: _reference!);

    final index = _steps.indexOf(_step);
    final titles = [context.tr('About you'), context.tr('Your school'), context.tr('Parent or guardian'), context.tr('Your password')];

    return PopScope(
      canPop: index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr('Become a member')),
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(4),
            child: LinearProgressIndicator(value: (index + 1) / _steps.length, backgroundColor: Brand.yellowWash, color: Brand.ink),
          ),
        ),
        body: SafeArea(
          child: Form(
            key: _forms[_step],
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(context.tr('Step :step of :total', {'step': index + 1, 'total': _steps.length}), style: const TextStyle(color: Brand.slate)),
                const SizedBox(height: 4),
                Text(titles[_step], style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 20),
                ...switch (_step) {
                  0 => _aboutYou(),
                  1 => _school(),
                  2 => _guardian(),
                  _ => _passwordStep(),
                },
                const SizedBox(height: 24),
                if (_step == 3)
                  BusyButton(label: context.tr('Send registration'), onPressed: _submit)
                else
                  FilledButton(onPressed: _next, child: Text(context.tr('Continue'))),
                if (index > 0) TextButton(onPressed: _back, child: Text(context.tr('Back'))),
              ],
            ),
          ),
        ),
      ),
    );
  }

  List<Widget> _aboutYou() => [
        TextFormField(
          controller: _name,
          textCapitalization: TextCapitalization.words,
          autofillHints: const [AutofillHints.name],
          decoration: InputDecoration(labelText: context.tr('Full name'), helperText: context.tr('As it appears on your school records.'), errorText: _error?.field('full_name')),
          validator: (value) => (value ?? '').trim().length < 3 ? context.tr('Enter your full name.') : null,
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _phone,
          keyboardType: TextInputType.phone,
          autofillHints: const [AutofillHints.telephoneNumber],
          decoration: InputDecoration(labelText: context.tr('Mobile number'), hintText: '0712 345 678', errorText: _error?.field('phone')),
          validator: _phoneError,
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(labelText: context.tr('Email (optional)'), errorText: _error?.field('email')),
          validator: (value) => (value ?? '').trim().isEmpty || (value ?? '').contains('@') ? null : context.tr('Enter a valid email address.'),
        ),
        const SizedBox(height: 16),
        InkWell(
          onTap: _pickBirthDate,
          borderRadius: BorderRadius.circular(12),
          child: InputDecorator(
            decoration: InputDecoration(labelText: context.tr('Date of birth'), suffixIcon: const Icon(Icons.calendar_today_outlined), errorText: _error?.field('date_of_birth')),
            child: Text(_birthDate == null ? context.tr('Choose a date') : context.date(_birthDate)),
          ),
        ),
        const SizedBox(height: 16),
        Text(context.tr('Gender (optional)'), style: const TextStyle(color: Brand.slate)),
        const SizedBox(height: 8),
        Wrap(spacing: 8, children: [
          for (final option in {'female': 'Female', 'male': 'Male'}.entries)
            ChoiceChip(
              label: Text(context.tr(option.value)),
              selected: _gender == option.key,
              onSelected: (selected) => setState(() => _gender = selected ? option.key : null),
            ),
        ]),
      ];

  List<Widget> _school() => [
        Text(context.tr('Where do you study?'), style: const TextStyle(color: Brand.slate)),
        const SizedBox(height: 8),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final option in optionsOf(context, 'user_types').entries)
            ChoiceChip(
              label: Text(option.value),
              selected: _userType == option.key,
              onSelected: (_) => setState(() {
                if (_userType != option.key) _institution = null;
                _userType = option.key;
              }),
            ),
        ]),
        const SizedBox(height: 20),
        InkWell(
          onTap: _pickInstitution,
          borderRadius: BorderRadius.circular(12),
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: context.tr('School, college or university'),
              suffixIcon: const Icon(Icons.search),
              errorText: _error?.field('institution_id'),
            ),
            child: Text(_institution == null
                ? context.tr('Search for your institution')
                : '${_institution!.str('name')} · ${_institution!.str('region')}'),
          ),
        ),
        const SizedBox(height: 12),
        Text(context.tr('Only institutions with an active Tax Club are listed. Your patron confirms your membership.'), style: const TextStyle(color: Brand.slate)),
      ];

  List<Widget> _guardian() => [
        Text(context.tr('Because you are under 18, a parent or guardian must agree to your membership.'), style: Theme.of(context).textTheme.bodyLarge),
        const SizedBox(height: 16),
        TextFormField(
          controller: _guardianName,
          textCapitalization: TextCapitalization.words,
          decoration: InputDecoration(labelText: context.tr('Parent or guardian\'s name'), errorText: _error?.field('guardian_name')),
          validator: (value) => (value ?? '').trim().length < 3 ? context.tr('Enter their full name.') : null,
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _guardianPhone,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: context.tr('Parent or guardian\'s phone'), hintText: '0712 345 678', errorText: _error?.field('guardian_phone')),
          validator: _phoneError,
        ),
        const SizedBox(height: 16),
        Wrap(spacing: 8, children: [
          for (final option in optionsOf(context, 'guardian_relationships').entries)
            ChoiceChip(label: Text(option.value), selected: _relationship == option.key, onSelected: (_) => setState(() => _relationship = option.key)),
        ]),
        const SizedBox(height: 8),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: _consent,
          onChanged: (value) => setState(() => _consent = value ?? false),
          title: Text(context.tr('My parent or guardian agrees that I can join the Tax Club.')),
          controlAffinity: ListTileControlAffinity.leading,
        ),
      ];

  List<Widget> _passwordStep() => [
        TextFormField(
          controller: _password,
          obscureText: true,
          autofillHints: const [AutofillHints.newPassword],
          decoration: InputDecoration(labelText: context.tr('Password'), helperText: context.tr('At least 8 characters.'), errorText: _error?.field('password')),
          validator: (value) => (value ?? '').length < 8 ? context.tr('Use at least 8 characters.') : null,
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _confirm,
          obscureText: true,
          decoration: InputDecoration(labelText: context.tr('Confirm password')),
          validator: (value) => value != _password.text ? context.tr('The passwords do not match.') : null,
        ),
        const SizedBox(height: 8),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: _agree,
          onChanged: (value) => setState(() => _agree = value ?? false),
          title: Text(context.tr('I will follow the Tax Club rules: be respectful, keep personal details private and report anything that worries me.')),
          controlAffinity: ListTileControlAffinity.leading,
        ),
      ];
}

class _Submitted extends StatelessWidget {
  const _Submitted({required this.reference});

  final String reference;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const SizedBox(height: 40),
            const Icon(Icons.check_circle, size: 80, color: Brand.success),
            const SizedBox(height: 16),
            Text(context.tr('Registration sent'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            Text(
              context.tr('Your patron or a TRA coordinator will confirm your membership. We will send you an SMS when you can sign in.'),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 24),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  Text(context.tr('Your reference'), style: const TextStyle(color: Brand.slate)),
                  const SizedBox(height: 4),
                  SelectableText(reference, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, letterSpacing: 1)),
                ]),
              ),
            ),
            const SizedBox(height: 32),
            FilledButton(onPressed: () => context.go('/welcome'), child: Text(context.tr('Done'))),
          ],
        ),
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
              decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: context.tr('Type the name of your institution')),
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
              load: (page) => context.api.paged('institutions', (row) => row, query: {'type': widget.type, 'search': _search, 'page': page, 'per_page': 30}),
              empty: EmptyPanel(message: context.tr('No institution found. Ask your patron whether your Tax Club is registered.'), icon: Icons.school_outlined),
              itemBuilder: (context, item, _) => ListTile(
                leading: const Icon(Icons.school_outlined),
                title: Text(item.str('name')),
                subtitle: Text([item.str('district'), item.str('region')].where((part) => part.isNotEmpty).join(', ')),
                onTap: () => Navigator.pop(context, item),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
