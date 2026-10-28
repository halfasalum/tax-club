import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/session.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// "My membership card" (livewire/member/card.blade.php). Kept on the
/// phone so it also works offline.
class CardScreen extends StatefulWidget {
  const CardScreen({super.key});

  @override
  State<CardScreen> createState() => _CardScreenState();
}

class _CardScreenState extends State<CardScreen> {
  static const _cacheKey = 'membership_card';
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();
  bool _offline = false;

  Future<Map<String, dynamic>> _load() async {
    final api = context.api;
    final prefs = await SharedPreferences.getInstance();
    try {
      final card = ((await api.get('membership/card')) as Map)
          .cast<String, dynamic>();
      await prefs.setString(_cacheKey, jsonEncode(card));
      _offline = false;
      return card;
    } on ApiException catch (error) {
      final cached = prefs.getString(_cacheKey);
      if (error.isOffline && cached != null) {
        _offline = true;
        return (jsonDecode(cached) as Map).cast<String, dynamic>();
      }
      rethrow;
    }
  }

  Future<void> _reissue() async {
    if (!await confirm(
      context,
      context.tr(
        'Replace your QR code? Old copies of your card will stop working.',
      ),
      action: context.tr('Get a new QR code'),
    )) {
      return;
    }
    if (!mounted) return;
    final result = await runAction(
      context,
      () => context.api.post('membership/card/reissue'),
      showServerMessage: true,
    );
    if (result != null) _loader.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final photo = session.user.strOrNull('photo_url');

    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: _load,
        builder: (context, card, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              context.tr('My membership card'),
              subtitle: context.tr(
                'Show this card at Tax Club events. Officers scan the QR code to check that your membership is active.',
              ),
              back: context.tr('My Tax Club'),
              onBack: () => context.go('/home'),
            ),
            if (_offline) ...[
              const SizedBox(height: 12),
              Pill.tone(context.tr('Offline: showing your saved card'), 'warn'),
            ],
            const SizedBox(height: 28),
            MembershipCardView(
              name: card.str('full_name'),
              number: card.str('membership_id'),
              institution: card.obj('institution')?.str('name'),
              category: labelOf(
                context,
                'user_types',
                card.strOrNull('user_type'),
              ),
              memberSince: card.date('member_since'),
              statusLabel: card.str('status_label'),
              valid: card.flag('is_valid'),
              qrData: card.strOrNull('verify_url'),
              photo: photo == null
                  ? null
                  : NetworkImage(photo, headers: session.api.authHeaders),
            ),
            const SizedBox(height: 22),
            if (card.strOrNull('verify_url') != null)
              WebButton(
                context.tr('Test my QR code'),
                kind: BtnKind.ghost,
                onPressed: () => openLink(context, card.str('verify_url')),
              ),
            if (card.flag('is_valid') && !_offline) ...[
              const SizedBox(height: 22),
              WebCard(
                padding: EdgeInsets.zero,
                child: ExpansionTile(
                  shape: const Border(),
                  tilePadding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 6,
                  ),
                  childrenPadding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
                  title: Text(
                    context.tr('Lost your card or shared a photo of it?'),
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 17,
                    ),
                  ),
                  expandedCrossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr(
                        'Get a new QR code. Your membership number stays the same, but printouts and photos of your old card will stop verifying.',
                      ),
                      style: const TextStyle(
                        color: Brand.slate,
                        fontSize: 16,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 14),
                    WebButton(
                      context.tr('Get a new QR code'),
                      kind: BtnKind.ghost,
                      onPressed: _reissue,
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
