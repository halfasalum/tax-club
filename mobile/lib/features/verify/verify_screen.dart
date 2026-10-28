import 'package:flutter/material.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

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
          ? await context.api.post('membership/verify', {'membership_number': number})
          : await context.api.post('certificates/verify', {'number': number});
      if (mounted) setState(() => _result = dataOf(envelope));
    } catch (error) {
      if (mounted) showMessage(context, errorText(context, error), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;
    final valid = result != null && (result.flag('is_valid') || result.str('result') == 'valid');

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Check a card or certificate'))),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          SegmentedButton<String>(
            showSelectedIcon: false,
            segments: [
              ButtonSegment(value: 'membership', label: Text(context.tr('Membership card'))),
              ButtonSegment(value: 'certificate', label: Text(context.tr('Certificate'))),
            ],
            selected: {_kind},
            onSelectionChanged: (value) => setState(() {
              _kind = value.first;
              _result = null;
            }),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _number,
            textCapitalization: TextCapitalization.characters,
            onSubmitted: (_) => _check(),
            decoration: InputDecoration(
              labelText: context.tr(_kind == 'membership' ? 'Membership number' : 'Certificate number'),
              hintText: _kind == 'membership' ? 'TC-24-DSM-00123-7' : 'TCC-24-XXXX-XXXX',
            ),
          ),
          const SizedBox(height: 16),
          BusyButton(label: context.tr('Check'), icon: Icons.search, onPressed: _check),
          const SizedBox(height: 24),
          if (result != null)
            Tile(
              color: valid ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Icon(valid ? Icons.verified : Icons.error_outline, color: valid ? Brand.success : Brand.danger, size: 32),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      context.tr(switch (result.str('result')) {
                        'valid' => _kind == 'membership' ? 'Valid member' : 'Valid certificate',
                        'revoked' => 'This certificate was withdrawn',
                        'not_found' => 'Not found',
                        'mistyped' => 'This number has a typing mistake. Check it and try again.',
                        'not_valid' => 'Not a valid member',
                        _ => valid ? 'Valid member' : 'Not a valid member',
                      }),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                  ),
                ]),
                const SizedBox(height: 8),
                if (result.strOrNull('display_name') != null) Text(result.str('display_name')),
                if (result.strOrNull('holder') != null) Text(result.str('holder')),
                if (result.strOrNull('title') != null) Text(result.str('title'), style: const TextStyle(fontWeight: FontWeight.w600)),
                if (result.strOrNull('subject') != null) Text(result.str('subject')),
                if (result.strOrNull('issued_at') != null) Text(context.tr('Issued :date', {'date': context.date(result.date('issued_at'))})),
              ]),
            ),
        ],
      ),
    );
  }
}
