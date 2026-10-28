import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/session.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';
import '../auth/auth_screens.dart';

/// "My profile" (livewire/member/profile.blade.php): photo, membership,
/// alumni request, contact and preferences, notifications, phone and
/// password, all on one page as on the website.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  int _quizzes = 0;

  @override
  void initState() {
    super.initState();
    final session = context.read<Session>();
    session.refreshUser();
    session.api
        .get('dashboard')
        .then((data) {
          if (mounted && data is Map) {
            setState(
              () => _quizzes = (data['quiz_attempts'] as num?)?.toInt() ?? 0,
            );
          }
        })
        .catchError((_) {});
  }

  Future<void> _changePhoto() async {
    final source = await pickOne(context, context.tr('Photo'), {
      'camera': context.tr('Take a photo'),
      'gallery': context.tr('Choose from gallery'),
      if (context.session.user.flag('has_photo'))
        'remove': context.tr('Remove photo'),
    });
    if (source == null || !mounted) return;

    if (source == 'remove') {
      final envelope = await runAction(
        context,
        () => context.api.delete('profile/photo'),
      );
      if (envelope != null && mounted) {
        context.session.updateUser(dataOf(envelope));
      }
      return;
    }

    final image = await ImagePicker().pickImage(
      source: source == 'camera' ? ImageSource.camera : ImageSource.gallery,
      maxWidth: 1200,
      imageQuality: 85,
    );
    if (image == null || !mounted) return;
    final bytes = await image.readAsBytes();
    if (!mounted) return;
    final form = FormData.fromMap({
      'photo': MultipartFile.fromBytes(
        bytes,
        filename: image.name.isEmpty ? 'photo.jpg' : image.name,
      ),
    });
    final envelope = await runAction(
      context,
      () => context.api.post('profile/photo', form),
    );
    if (envelope != null && mounted) {
      context.session.updateUser(dataOf(envelope));
    }
  }

  Future<void> _alumni(bool requested) async {
    if (!requested &&
        !await confirm(
          context,
          context.tr('Ask to become alumni?'),
          action: context.tr('Ask to become alumni'),
        )) {
      return;
    }
    if (!mounted) return;
    final envelope = await runAction(
      context,
      () => requested
          ? context.api.delete('profile/alumni-request')
          : context.api.post('profile/alumni-request'),
    );
    if (envelope != null && mounted) {
      context.session.updateUser(dataOf(envelope));
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final user = session.user;
    final photo = user.strOrNull('photo_url');
    final requested = user.strOrNull('alumni_requested_at') != null;

    Widget detail(String label, String value) => Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: Brand.slate,
            ),
          ),
          Text(
            value.isEmpty ? '—' : value,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );

    return MemberScaffold(
      body: MemberPage(
        onRefresh: session.refreshUser,
        children: [
          PageTitle(context.tr('My profile')),
          const SizedBox(height: 24),
          WebCard(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                Container(
                  width: 150,
                  height: 150,
                  decoration: BoxDecoration(
                    color: Brand.yellow,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: Brand.ink, width: 4),
                    image: photo == null
                        ? null
                        : DecorationImage(
                            image: NetworkImage(
                              photo,
                              headers: session.api.authHeaders,
                            ),
                            fit: BoxFit.cover,
                          ),
                  ),
                  alignment: Alignment.center,
                  child: photo == null
                      ? Text(
                          initialsOf(user.str('full_name', '?')),
                          style: const TextStyle(
                            fontSize: 52,
                            fontWeight: FontWeight.w900,
                          ),
                        )
                      : null,
                ),
                const SizedBox(height: 16),
                Text(
                  user.str('full_name'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  user.str('membership_id'),
                  style: const TextStyle(
                    fontFamily: 'IBMPlexMono',
                    color: Brand.slate,
                  ),
                ),
                const SizedBox(height: 16),
                WebButton(
                  context.tr(photo == null ? 'Add a photo' : 'Change photo'),
                  kind: BtnKind.ghost,
                  onPressed: _changePhoto,
                ),
                const SizedBox(height: 10),
                Text(
                  context.tr(
                    'Use a clear photo of your face. Only you and Tax Club officers can see it.',
                  ),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Brand.slate, fontSize: 14),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: StatBox(
                  label: context.tr('Points'),
                  value: context.number(user.integer('total_points')),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: StatBox(
                  label: context.tr('Quizzes taken'),
                  value: context.number(_quizzes),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          WebCard(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.tr('Membership'),
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 16),
                detail(
                  context.tr('Status'),
                  labelOf(
                    context,
                    'account_statuses',
                    user.strOrNull('account_status'),
                  ),
                ),
                detail(
                  context.tr('Category'),
                  labelOf(context, 'user_types', user.strOrNull('user_type')),
                ),
                detail(
                  context.tr('Institution'),
                  [
                    user.obj('institution')?.str('name') ?? '',
                    user.obj('institution')?.str('region') ?? '',
                  ].where((s) => s.isNotEmpty).join(', '),
                ),
                detail(
                  context.tr('Member since'),
                  context.date(user.date('member_since')),
                ),
                detail(
                  context.tr('Date of birth'),
                  context.date(user.date('date_of_birth')),
                ),
                detail(context.tr('Mobile number'), user.str('phone')),
                if (user.obj('guardian') != null)
                  detail(
                    context.tr('Parent or guardian'),
                    '${user.obj('guardian')!.str('name')} · ${user.obj('guardian')!.str('phone')}',
                  ),
                MistBox(
                  context.tr(
                    'Is your name, date of birth or institution wrong? Ask your Tax Club patron to correct it.',
                  ),
                ),
              ],
            ),
          ),
          if (user.flag('can_request_alumni') || requested) ...[
            const SizedBox(height: 14),
            WebCard(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.tr('Finished your studies?'),
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    context.tr(
                      'Become Tax Club alumni and stay part of the community. You keep your membership number, points and certificates.',
                    ),
                    style: const TextStyle(
                      color: Brand.slate,
                      fontSize: 16,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 14),
                  WebButton(
                    context.tr(
                      requested ? 'Cancel request' : 'Ask to become alumni',
                    ),
                    kind: BtnKind.ghost,
                    onPressed: () => _alumni(requested),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 14),
          _ContactCard(user: user),
          const SizedBox(height: 14),
          const _NotificationCard(),
          const SizedBox(height: 14),
          const _PhoneCard(),
          const SizedBox(height: 14),
          const _PasswordCard(),
          const SizedBox(height: 24),
          WebButton(
            context.tr('Sign out'),
            kind: BtnKind.ghost,
            icon: Icons.logout,
            onPressed: () async {
              if (await confirm(
                context,
                context.tr('Sign out of the Tax Club app?'),
                action: context.tr('Sign out'),
              )) {
                await session.logout();
              }
            },
          ),
          const SizedBox(height: 18),
          FutureBuilder<PackageInfo>(
            future: PackageInfo.fromPlatform(),
            builder: (context, snapshot) => Text(
              snapshot.hasData
                  ? context.tr('Version :version', {
                      'version':
                          '${snapshot.data!.version} (${snapshot.data!.buildNumber})',
                    })
                  : '',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Brand.slate, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.title, this.text, required this.children});

  final String title;
  final String? text;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return WebCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
          ),
          if (text != null) ...[
            const SizedBox(height: 8),
            Text(
              text!,
              style: const TextStyle(
                color: Brand.slate,
                fontSize: 16,
                height: 1.5,
              ),
            ),
          ],
          const SizedBox(height: 18),
          ...children,
        ],
      ),
    );
  }
}

class _ContactCard extends StatefulWidget {
  const _ContactCard({required this.user});

  final Map<String, dynamic> user;

  @override
  State<_ContactCard> createState() => _ContactCardState();
}

class _ContactCardState extends State<_ContactCard> {
  late final _email = TextEditingController(
    text: widget.user.strOrNull('email'),
  );
  late String? _gender = widget.user.strOrNull('gender');
  late String _smsLanguage = widget.user.str('preferred_locale', 'sw');
  ApiException? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return _Card(
      title: context.tr('Contact and preferences'),
      children: [
        WebField(
          label: context.tr('Email'),
          controller: _email,
          optional: true,
          keyboard: TextInputType.emailAddress,
          error: _error?.field('email'),
        ),
        Text(
          context.tr('Gender'),
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final option in {
              '': 'Prefer not to say',
              'female': 'Female',
              'male': 'Male',
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
        const SizedBox(height: 18),
        Text(
          context.tr('Language for SMS'),
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          children: [
            for (final option in {'sw': 'Kiswahili', 'en': 'English'}.entries)
              ChoiceChip(
                label: Text(option.value),
                selected: _smsLanguage == option.key,
                onSelected: (_) => setState(() => _smsLanguage = option.key),
              ),
          ],
        ),
        const SizedBox(height: 20),
        WebButton(
          context.tr('Save details'),
          onPressed: () async {
            setState(() => _error = null);
            try {
              final envelope = await context.api.put('profile', {
                'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
                'gender': _gender,
                'preferred_locale': _smsLanguage,
              });
              if (!context.mounted) return;
              context.session.updateUser(dataOf(envelope));
              showMessage(context, context.tr('Saved.'));
            } on ApiException catch (error) {
              setState(() => _error = error);
            }
          },
        ),
      ],
    );
  }
}

class _NotificationCard extends StatefulWidget {
  const _NotificationCard();

  @override
  State<_NotificationCard> createState() => _NotificationCardState();
}

class _NotificationCardState extends State<_NotificationCard> {
  List<Map<String, dynamic>>? _rows;

  @override
  void initState() {
    super.initState();
    context
        .read<Session>()
        .api
        .get('notification-preferences')
        .then((data) {
          if (mounted) setState(() => _rows = listOf(data));
        })
        .catchError((_) {});
  }

  Future<void> _set(Map<String, dynamic> row, {bool? sms, bool? inbox}) async {
    final envelope = await runAction(
      context,
      () => context.api.put('notification-preferences', {
        'category': row.str('category'),
        'sms': sms ?? row.flag('sms'),
        'inbox': inbox ?? row.flag('inbox'),
      }),
    );
    if (envelope != null && mounted) {
      setState(() => _rows = listOf(envelope['data']));
    }
  }

  @override
  Widget build(BuildContext context) {
    final rows = _rows;
    return _Card(
      title: context.tr('Notifications'),
      text: context.tr(
        'Messages about your account, membership and security always reach you. You can choose for the rest.',
      ),
      children: [
        if (rows == null) const Center(child: CircularProgressIndicator()),
        for (final row in rows ?? const <Map<String, dynamic>>[])
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Container(
              padding: const EdgeInsets.fromLTRB(16, 14, 8, 8),
              decoration: BoxDecoration(
                color: Brand.mist,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    row.str('label'),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  Text(
                    row.str('description'),
                    style: const TextStyle(color: Brand.slate, fontSize: 14),
                  ),
                  Row(
                    children: [
                      Expanded(
                        child: _Toggle(
                          label: 'SMS',
                          value: row.flag('sms'),
                          onChanged: (value) => _set(row, sms: value),
                        ),
                      ),
                      Expanded(
                        child: _Toggle(
                          label: context.tr('Inbox'),
                          value: row.flag('inbox'),
                          onChanged: (value) => _set(row, inbox: value),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _Toggle extends StatelessWidget {
  const _Toggle({
    required this.label,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Checkbox(value: value, onChanged: (v) => onChanged(v ?? false)),
      Flexible(
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
      ),
    ],
  );
}

class _PhoneCard extends StatefulWidget {
  const _PhoneCard();

  @override
  State<_PhoneCard> createState() => _PhoneCardState();
}

class _PhoneCardState extends State<_PhoneCard> {
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
    return _Card(
      title: context.tr('Change mobile number'),
      text: context.tr(
        'We send a code to the new number to check that it is yours.',
      ),
      children: [
        if (_error != null && _error!.errors.isEmpty)
          ErrorLine(errorText(context, _error!)),
        if (!_sent) ...[
          WebField(
            label: context.tr('New mobile number'),
            controller: _phone,
            hint: '0712 345 678',
            keyboard: TextInputType.phone,
            error: _error?.field('phone'),
          ),
          WebButton(
            context.tr('Send code'),
            kind: BtnKind.ghost,
            onPressed: () async {
              setState(() => _error = null);
              try {
                await context.api.post('profile/phone', {
                  'phone': _phone.text.trim(),
                });
                setState(() => _sent = true);
              } on ApiException catch (error) {
                setState(() => _error = error);
              }
            },
          ),
        ] else ...[
          WebField(
            label: context.tr('Code from the SMS'),
            controller: _code,
            keyboard: TextInputType.number,
            error: _error?.field('code'),
          ),
          Row(
            children: [
              Expanded(
                child: WebButton(
                  context.tr('Confirm number'),
                  onPressed: () async {
                    setState(() => _error = null);
                    try {
                      final envelope = await context.api.post(
                        'profile/phone/confirm',
                        {'code': _code.text.trim()},
                      );
                      if (!context.mounted) return;
                      context.session.updateUser(dataOf(envelope));
                      setState(() => _sent = false);
                      showMessage(
                        context,
                        context.tr('Your phone number was changed.'),
                      );
                    } on ApiException catch (error) {
                      setState(() => _error = error);
                    }
                  },
                ),
              ),
              const SizedBox(width: 10),
              WebButton(
                context.tr('Cancel'),
                kind: BtnKind.ghost,
                expand: false,
                onPressed: () async => setState(() => _sent = false),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _PasswordCard extends StatefulWidget {
  const _PasswordCard();

  @override
  State<_PasswordCard> createState() => _PasswordCardState();
}

class _PasswordCardState extends State<_PasswordCard> {
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
    return _Card(
      title: context.tr('Change password'),
      children: [
        if (_error != null && _error!.errors.isEmpty)
          ErrorLine(errorText(context, _error!)),
        WebField(
          label: context.tr('Current password'),
          controller: _current,
          password: true,
          error: _error?.field('current_password'),
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
        WebButton(
          context.tr('Change password'),
          kind: BtnKind.ghost,
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
    );
  }
}
