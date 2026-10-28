import 'package:flutter/material.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';
import '../auth/auth_screens.dart';

/// Check a membership card or a certificate by its number.
class VerifyScreen extends StatefulWidget {
  const VerifyScreen({super.key});

  @override
  State<VerifyScreen> createState() => _VerifyScreenState();
}

class _VerifyScreenState extends State<VerifyScreen> {
  final _number = TextEditingController();
  String _kind = 'membership';
  Map<String, dynamic>? _result;

  @override
  void dispose() {
    _number.dispose();
    super.dispose();
  }

  Future<void> _check() async {
    final number = _number.text.trim();
    if (number.isEmpty) return;
    setState(() => _result = null);
    try {
      final envelope = _kind == 'membership'
          ? await context.api.post('membership/verify', {
              'membership_number': number,
            })
          : await context.api.post('certificates/verify', {'number': number});
      if (mounted) setState(() => _result = dataOf(envelope));
    } catch (error) {
      if (mounted) showMessage(context, errorText(context, error), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final valid =
        result != null &&
        (result.flag('is_valid') || result.str('result') == 'valid');

    return AuthScaffold(
      title: context.tr('Check a card or certificate'),
      children: [
        SegmentedButton<String>(
          showSelectedIcon: false,
          style: SegmentedButton.styleFrom(
            selectedBackgroundColor: Brand.ink,
            selectedForegroundColor: Brand.yellow,
            textStyle: const TextStyle(fontWeight: FontWeight.w800),
          ),
          segments: [
            ButtonSegment(
              value: 'membership',
              label: Text(context.tr('Membership card')),
            ),
            ButtonSegment(
              value: 'certificate',
              label: Text(context.tr('Certificate')),
            ),
          ],
          selected: {_kind},
          onSelectionChanged: (value) => setState(() {
            _kind = value.first;
            _result = null;
          }),
        ),
        const SizedBox(height: 22),
        WebField(
          label: context.tr(
            _kind == 'membership' ? 'Membership number' : 'Certificate number',
          ),
          controller: _number,
          hint: _kind == 'membership'
              ? 'TC-24-DSM-00123-7'
              : 'TCC-24-XXXX-XXXX',
          capitalization: TextCapitalization.characters,
          onSubmitted: (_) => _check(),
        ),
        WebButton(context.tr('Check'), onPressed: _check),
        if (result != null) ...[
          const SizedBox(height: 22),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: valid ? const Color(0xFFDCFCE7) : const Color(0xFFFEE4E2),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: valid ? Brand.success : Brand.danger,
                width: 2,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      valid ? Icons.verified : Icons.error_outline,
                      color: valid ? Brand.success : Brand.danger,
                      size: 30,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        context.tr(switch (result.str('result')) {
                          'valid' =>
                            _kind == 'membership'
                                ? 'Valid member'
                                : 'Valid certificate',
                          'revoked' => 'This certificate was withdrawn',
                          'not_found' => 'Not found',
                          'mistyped' => 'This number has a typing mistake. Check it and try again.',
                          _ => 'Not a valid member',
                        }),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                for (final key in [
                  'display_name',
                  'holder',
                  'title',
                  'subject',
                ])
                  if (result.strOrNull(key) != null)
                    Text(
                      result.str(key),
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                if (result.strOrNull('issued_at') != null)
                  Text(
                    context.tr('Issued :date', {
                      'date': context.date(result.date('issued_at')),
                    }),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
