import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// The digital membership card. Kept on the phone so it also works offline.
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
      final card = ((await api.get('membership/card')) as Map).cast<String, dynamic>();
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
    if (!await confirm(context, context.tr('Get a new QR code? Your old code will stop working. Do this if your card was lost or someone else has a copy.'), action: context.tr('Get a new code'))) return;
    if (!mounted) return;
    final result = await runAction(context, () => context.api.post('membership/card/reissue'), showServerMessage: true);
    if (result != null) _loader.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Membership card'))),
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: _load,
        builder: (context, card, reload) {
          final valid = card.flag('is_valid');
          final verifyUrl = card.strOrNull('verify_url');
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_offline)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Pill.tone(context.tr('Offline: showing your saved card'), 'warn'),
                  ),
                Container(
                  decoration: BoxDecoration(
                    color: Brand.yellow,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: const [BoxShadow(color: Color(0x22000000), blurRadius: 16, offset: Offset(0, 6))],
                  ),
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Image.asset('assets/images/tra-logo.png', height: 44),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            const Text('TRA Tax Club', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                            Text(context.tr('Membership card'), style: const TextStyle(color: Brand.charcoal)),
                          ]),
                        ),
                        Pill(card.str('status_label'), color: valid ? Brand.success : Brand.danger, filled: true),
                      ]),
                      const SizedBox(height: 20),
                      Text(card.str('full_name'), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
                      Text(card.str('membership_id'), style: const TextStyle(fontSize: 18, letterSpacing: 1.2, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 12),
                      if (card.obj('institution') != null) Text(card.obj('institution')!.str('name'), style: const TextStyle(fontWeight: FontWeight.w600)),
                      Text([labelOf(context, 'user_types', card.strOrNull('user_type')), card.obj('institution')?.str('region') ?? ''].where((s) => s.isNotEmpty).join(' · ')),
                      if (card.strOrNull('member_since') != null)
                        Text(context.tr('Member since :date', {'date': context.date(card.date('member_since'))}), style: const TextStyle(color: Brand.charcoal)),
                      const SizedBox(height: 20),
                      if (valid && verifyUrl != null)
                        Center(
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
                            child: QrImageView(data: verifyUrl, size: 200, backgroundColor: Colors.white),
                          ),
                        )
                      else
                        Text(context.tr('This card is not valid right now.'), style: const TextStyle(fontWeight: FontWeight.w700, color: Brand.danger)),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  context.tr('Show this QR code at Tax Club events. Officers scan it to check your membership.'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Brand.slate),
                ),
                const SizedBox(height: 20),
                if (valid && !_offline) BusyButton(label: context.tr('Lost your card? Get a new QR code'), icon: Icons.refresh, outlined: true, onPressed: _reissue),
              ],
            ),
          );
        },
      ),
    );
  }
}
