import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../auth/welcome_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  @override
  void initState() {
    super.initState();
    context.read<Session>().refreshUser();
  }

  Future<void> _changePhoto() async {
    final source = await pickOne(context, context.tr('Profile photo'), {
      'camera': context.tr('Take a photo'),
      'gallery': context.tr('Choose from gallery'),
      if (context.session.user.flag('has_photo')) 'remove': context.tr('Remove photo'),
    });
    if (source == null || !mounted) return;

    if (source == 'remove') {
      final envelope = await runAction(context, () => context.api.delete('profile/photo'));
      if (envelope != null && mounted) context.session.updateUser(dataOf(envelope));
      return;
    }

    final image = await ImagePicker().pickImage(source: source == 'camera' ? ImageSource.camera : ImageSource.gallery, maxWidth: 1200, imageQuality: 85);
    if (image == null || !mounted) return;
    final bytes = await image.readAsBytes();
    if (!mounted) return;
    final form = FormData.fromMap({'photo': MultipartFile.fromBytes(bytes, filename: image.name.isEmpty ? 'photo.jpg' : image.name)});
    final envelope = await runAction(context, () => context.api.post('profile/photo', form), success: context.tr('Photo updated.'));
    if (envelope != null && mounted) context.session.updateUser(dataOf(envelope));
  }

  Future<void> _editDetails(Map<String, dynamic> user) async {
    final updated = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _EditDetails(user: user),
    );
    if (updated != null && mounted) context.session.updateUser(updated);
  }

  Future<void> _alumni(bool requested) async {
    final message = requested
        ? context.tr('Cancel your request to become an alumnus?')
        : context.tr('Finished school, college or university? Ask your patron to move you to the alumni network.');
    if (!await confirm(context, message, action: context.tr(requested ? 'Cancel request' : 'Send request'))) return;
    if (!mounted) return;
    final envelope = await runAction(
      context,
      () => requested ? context.api.delete('profile/alumni-request') : context.api.post('profile/alumni-request'),
      success: context.tr(requested ? 'Request cancelled.' : 'Request sent to your patron.'),
    );
    if (envelope != null && mounted) context.session.updateUser(dataOf(envelope));
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final user = session.user;
    final photo = user.strOrNull('photo_url');
    final guardian = user.obj('guardian');

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My profile'))),
      body: RefreshIndicator(
        onRefresh: session.refreshUser,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Center(
              child: Stack(children: [
                CircleAvatar(
                  radius: 52,
                  backgroundColor: Brand.yellow,
                  foregroundImage: photo == null ? null : NetworkImage(photo, headers: session.api.authHeaders),
                  child: Text(user.str('full_name', '?').characters.first, style: const TextStyle(fontSize: 40, fontWeight: FontWeight.w700, color: Brand.ink)),
                ),
                Positioned(
                  right: 0,
                  bottom: 0,
                  child: IconButton.filled(tooltip: context.tr('Change photo'), onPressed: _changePhoto, icon: const Icon(Icons.camera_alt_outlined, size: 20)),
                ),
              ]),
            ),
            const SizedBox(height: 12),
            Text(user.str('full_name'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            Text(user.str('membership_id'), textAlign: TextAlign.center, style: const TextStyle(color: Brand.slate)),
            const SizedBox(height: 16),
            Card(
              child: Column(children: [
                _Detail(icon: Icons.school_outlined, label: context.tr('Institution'), value: user.obj('institution')?.str('name') ?? ''),
                _Detail(icon: Icons.category_outlined, label: context.tr('Member type'), value: labelOf(context, 'user_types', user.strOrNull('user_type'))),
                _Detail(icon: Icons.phone_outlined, label: context.tr('Phone'), value: user.str('phone'), onTap: () => context.push('/settings/phone')),
                _Detail(icon: Icons.email_outlined, label: context.tr('Email'), value: user.strOrNull('email') ?? context.tr('Not added'), onTap: () => _editDetails(user)),
                _Detail(icon: Icons.cake_outlined, label: context.tr('Date of birth'), value: context.date(user.date('date_of_birth'))),
                _Detail(icon: Icons.person_outline, label: context.tr('Gender'), value: user.strOrNull('gender') == null ? context.tr('Not given') : context.tr(humanize(user.str('gender'))), onTap: () => _editDetails(user)),
                _Detail(icon: Icons.star_outline, label: context.tr('Points'), value: context.number(user.integer('total_points'))),
                if (user.date('member_since') != null) _Detail(icon: Icons.verified_outlined, label: context.tr('Member since'), value: context.date(user.date('member_since'))),
              ]),
            ),
            if (guardian != null) ...[
              const SizedBox(height: 12),
              Card(
                child: Column(children: [
                  _Detail(icon: Icons.family_restroom, label: context.tr('Parent or guardian'), value: '${guardian.str('name')} · ${guardian.str('phone')}'),
                ]),
              ),
            ],
            const SizedBox(height: 8),
            Text(context.tr('To correct your name, school or date of birth, ask your patron.'), style: const TextStyle(color: Brand.slate, fontSize: 12)),
            const SizedBox(height: 16),
            if (user.flag('can_request_alumni') || user.strOrNull('alumni_requested_at') != null)
              BusyButton(
                label: context.tr(user.strOrNull('alumni_requested_at') != null ? 'Alumni request sent · Cancel' : 'I have finished my studies'),
                icon: Icons.school,
                outlined: true,
                onPressed: () => _alumni(user.strOrNull('alumni_requested_at') != null),
              ),
            const SizedBox(height: 12),
            BusyButton(label: context.tr('Settings'), icon: Icons.settings_outlined, outlined: true, onPressed: () async => context.push('/settings')),
          ],
        ),
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.icon, required this.label, required this.value, this.onTap});

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(label, style: const TextStyle(fontSize: 13, color: Brand.slate)),
      subtitle: Text(value.isEmpty ? '—' : value, style: const TextStyle(fontSize: 16, color: Brand.ink)),
      trailing: onTap == null ? null : const Icon(Icons.edit_outlined, size: 18),
      onTap: onTap,
    );
  }
}

class _EditDetails extends StatefulWidget {
  const _EditDetails({required this.user});

  final Map<String, dynamic> user;

  @override
  State<_EditDetails> createState() => _EditDetailsState();
}

class _EditDetailsState extends State<_EditDetails> {
  late final _email = TextEditingController(text: widget.user.strOrNull('email'));
  late String? _gender = widget.user.strOrNull('gender');
  ApiException? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(context.tr('Edit my details'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 12),
        TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: InputDecoration(labelText: context.tr('Email (optional)'), errorText: _error?.field('email'))),
        const SizedBox(height: 12),
        Wrap(spacing: 8, children: [
          for (final option in {'female': 'Female', 'male': 'Male'}.entries)
            ChoiceChip(label: Text(context.tr(option.value)), selected: _gender == option.key, onSelected: (on) => setState(() => _gender = on ? option.key : null)),
        ]),
        const SizedBox(height: 16),
        BusyButton(
          label: context.tr('Save'),
          onPressed: () async {
            setState(() => _error = null);
            try {
              final envelope = await context.api.put('profile', {
                'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
                'gender': _gender,
                'preferred_locale': context.session.locale,
              });
              if (context.mounted) Navigator.pop(context, dataOf(envelope));
            } on ApiException catch (error) {
              setState(() => _error = error);
            }
          },
        ),
      ]),
    );
  }
}

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final links = session.config.obj('links') ?? const {};

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Settings'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SectionTitle(context.tr('Language')),
          Text(context.tr('The app, SMS and notifications will use this language.'), style: const TextStyle(color: Brand.slate)),
          const SizedBox(height: 8),
          LanguageSwitch(session: session),
          const SizedBox(height: 16),
          Card(
            child: Column(children: [
              ListTile(leading: const Icon(Icons.notifications_outlined), title: Text(context.tr('Notifications and SMS')), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/settings/notifications')),
              ListTile(leading: const Icon(Icons.lock_outline), title: Text(context.tr('Change password')), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/settings/password')),
              ListTile(leading: const Icon(Icons.phone_outlined), title: Text(context.tr('Change phone number')), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/settings/phone')),
            ]),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(children: [
              ListTile(leading: const Icon(Icons.public), title: Text(context.tr('Tax Club website')), trailing: const Icon(Icons.open_in_new, size: 18), onTap: () => openLink(context, links.strOrNull('website') ?? session.api.baseUrl)),
              ListTile(leading: const Icon(Icons.info_outline), title: Text(context.tr('About Tax Club')), trailing: const Icon(Icons.open_in_new, size: 18), onTap: () => openLink(context, links.strOrNull('about') ?? '${session.api.baseUrl}/about')),
              ListTile(leading: const Icon(Icons.verified_outlined), title: Text(context.tr('Check a card or certificate')), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/verify')),
            ]),
          ),
          const SizedBox(height: 24),
          BusyButton(
            label: context.tr('Sign out'),
            icon: Icons.logout,
            outlined: true,
            onPressed: () async {
              if (await confirm(context, context.tr('Sign out of the Tax Club app?'), action: context.tr('Sign out'))) {
                await session.logout();
              }
            },
          ),
          const SizedBox(height: 24),
          FutureBuilder<PackageInfo>(
            future: PackageInfo.fromPlatform(),
            builder: (context, snapshot) => Text(
              snapshot.hasData ? context.tr('Version :version', {'version': '${snapshot.data!.version} (${snapshot.data!.buildNumber})'}) : '',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Brand.slate, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }
}

class NotificationSettingsScreen extends StatelessWidget {
  const NotificationSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Notifications and SMS'))),
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('notification-preferences')),
        builder: (context, rows, reload) => _PreferenceList(rows: rows),
      ),
    );
  }
}

class _PreferenceList extends StatefulWidget {
  const _PreferenceList({required this.rows});

  final List<Map<String, dynamic>> rows;

  @override
  State<_PreferenceList> createState() => _PreferenceListState();
}

class _PreferenceListState extends State<_PreferenceList> {
  late List<Map<String, dynamic>> _rows = widget.rows;

  Future<void> _set(Map<String, dynamic> row, {bool? sms, bool? inbox}) async {
    final envelope = await runAction(context, () => context.api.put('notification-preferences', {
          'category': row.str('category'),
          'sms': sms ?? row.flag('sms'),
          'inbox': inbox ?? row.flag('inbox'),
        }));
    if (envelope != null && mounted) setState(() => _rows = listOf(envelope['data']));
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(context.tr('Important messages about your account, safety and security are always sent.'), style: const TextStyle(color: Brand.slate)),
        const SizedBox(height: 12),
        for (final row in _rows)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(row.str('label'), style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text(row.str('description'), style: const TextStyle(color: Brand.slate, fontSize: 13)),
                    ]),
                  ),
                  SwitchListTile(dense: true, title: Text(context.tr('SMS')), value: row.flag('sms'), onChanged: (value) => _set(row, sms: value)),
                  SwitchListTile(dense: true, title: Text(context.tr('In the app')), value: row.flag('inbox'), onChanged: (value) => _set(row, inbox: value)),
                ]),
              ),
            ),
          ),
      ],
    );
  }
}

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  ApiException? _error;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Change password'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _current, obscureText: true, decoration: InputDecoration(labelText: context.tr('Current password'), errorText: _error?.field('current_password'))),
          const SizedBox(height: 16),
          TextField(controller: _password, obscureText: true, decoration: InputDecoration(labelText: context.tr('New password'), helperText: context.tr('At least 8 characters.'), errorText: _error?.field('password'))),
          const SizedBox(height: 16),
          TextField(controller: _confirm, obscureText: true, decoration: InputDecoration(labelText: context.tr('Confirm new password'))),
          if (_error != null && _error!.errors.isEmpty) ...[
            const SizedBox(height: 12),
            Text(errorText(context, _error!), style: const TextStyle(color: Brand.danger)),
          ],
          const SizedBox(height: 24),
          BusyButton(
            label: context.tr('Change password'),
            onPressed: () async {
              setState(() => _error = null);
              try {
                final envelope = await context.api.post('auth/change-password', {
                  'current_password': _current.text,
                  'password': _password.text,
                  'password_confirmation': _confirm.text,
                });
                if (!context.mounted) return;
                showMessage(context, '${envelope['message']}');
                // Every session is signed out after a password change.
                await context.session.signOutLocally();
              } on ApiException catch (error) {
                setState(() => _error = error);
              }
            },
          ),
        ],
      ),
    );
  }
}

class ChangePhoneScreen extends StatefulWidget {
  const ChangePhoneScreen({super.key});

  @override
  State<ChangePhoneScreen> createState() => _ChangePhoneScreenState();
}

class _ChangePhoneScreenState extends State<ChangePhoneScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  bool _sent = false;
  ApiException? _error;

  @override
  void dispose() {
    _phone.dispose();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Change phone number'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(context.tr(_sent ? 'Enter the code we sent to your new number.' : 'We will send a code by SMS to the new number to confirm it is yours.'), style: Theme.of(context).textTheme.bodyLarge),
          const SizedBox(height: 16),
          TextField(
            controller: _phone,
            enabled: !_sent,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(labelText: context.tr('New phone number'), hintText: '0712 345 678', errorText: _error?.field('phone')),
          ),
          if (_sent) ...[
            const SizedBox(height: 16),
            TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6, decoration: InputDecoration(labelText: context.tr('SMS code'), errorText: _error?.field('code'))),
          ],
          if (_error != null && _error!.errors.isEmpty) ...[
            const SizedBox(height: 12),
            Text(errorText(context, _error!), style: const TextStyle(color: Brand.danger)),
          ],
          const SizedBox(height: 24),
          BusyButton(
            label: context.tr(_sent ? 'Confirm' : 'Send code'),
            onPressed: () async {
              setState(() => _error = null);
              try {
                if (!_sent) {
                  await context.api.post('profile/phone', {'phone': _phone.text.trim()});
                  setState(() => _sent = true);
                } else {
                  final envelope = await context.api.post('profile/phone/confirm', {'code': _code.text.trim()});
                  if (!context.mounted) return;
                  context.session.updateUser(dataOf(envelope));
                  showMessage(context, context.tr('Your phone number was changed.'));
                  context.pop();
                }
              } on ApiException catch (error) {
                setState(() => _error = error);
              }
            },
          ),
        ],
      ),
    );
  }
}
