import 'package:flutter/material.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// Points, badges, certificates and rewards.
class AchievementsScreen extends StatelessWidget {
  const AchievementsScreen({super.key});

  Future<Map<String, dynamic>> _load(BuildContext context) async {
    final api = context.api;
    final results = await Future.wait([
      api.get('achievements'),
      api.get('badges').catchError((_) => <dynamic>[]),
    ]);
    return {...(results[0] as Map).cast<String, dynamic>(), 'badges': results[1]};
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My achievements'))),
      body: Loader<Map<String, dynamic>>(
        load: () => _load(context),
        builder: (context, data, reload) {
          final badges = data.list('badges');
          final earned = badges.where((badge) => badge.flag('earned')).length;
          final certificates = data.list('certificates');
          final rewards = data.list('rewards');

          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Tile(
                  color: Brand.yellow,
                  child: Row(children: [
                    const Icon(Icons.star_rounded, size: 44),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(context.number(data.integer('total_points')), style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800)),
                        Text(context.tr('points earned from lessons, quizzes and events')),
                      ]),
                    ),
                  ]),
                ),
                if (badges.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  SectionTitle(context.tr('Badges (:earned of :total)', {'earned': earned, 'total': badges.length})),
                  GridView.count(
                    crossAxisCount: 3,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 0.82,
                    children: [
                      for (final badge in badges)
                        Tooltip(
                          message: badge.str('description'),
                          triggerMode: TooltipTriggerMode.tap,
                          child: Opacity(
                            opacity: badge.flag('earned') ? 1 : 0.4,
                            child: Card(
                              child: Padding(
                                padding: const EdgeInsets.all(8),
                                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                                  CircleAvatar(
                                    radius: 24,
                                    backgroundColor: badge.flag('earned') ? Brand.yellow : Brand.line,
                                    child: Icon(badge.flag('earned') ? Icons.military_tech : Icons.lock_outline, color: Brand.ink),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(badge.str('name'), textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                ]),
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
                const SizedBox(height: 16),
                SectionTitle(context.tr('Certificates')),
                if (certificates.isEmpty)
                  Tile(child: Text(context.tr('Finish a learning module, attend an event or take part in a competition to earn a certificate.')))
                else
                  for (final certificate in certificates)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Tile(
                        onTap: () => openLink(context, certificate.str('verify_url')),
                        child: Row(children: [
                          const Icon(Icons.workspace_premium, size: 36, color: Color(0xFFCA8A04)),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(certificate.str('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                              Text(certificate.str('subject')),
                              Text('${certificate.str('number')} · ${context.date(certificate.date('issued_at'))}', style: const TextStyle(color: Brand.slate, fontSize: 12)),
                            ]),
                          ),
                          if (!certificate.flag('valid')) Pill.tone(context.tr('Withdrawn'), 'bad') else const Icon(Icons.open_in_new, size: 18),
                        ]),
                      ),
                    ),
                const SizedBox(height: 16),
                SectionTitle(context.tr('Rewards')),
                if (rewards.isEmpty)
                  Tile(child: Text(context.tr('Prizes you win in competitions appear here.')))
                else
                  Card(
                    child: Column(children: [
                      for (final reward in rewards)
                        ListTile(
                          leading: const Icon(Icons.card_giftcard),
                          title: Text(reward.str('reward')),
                          subtitle: reward.strOrNull('reason') == null ? null : Text(reward.str('reason')),
                          trailing: Pill.tone(labelOf(context, 'reward_statuses', reward.str('status')), toneOf(reward.str('status'))),
                        ),
                    ]),
                  ),
                const SizedBox(height: 24),
              ],
            ),
          );
        },
      ),
    );
  }
}
