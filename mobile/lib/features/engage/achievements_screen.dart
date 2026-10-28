import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// "My achievements" (livewire/member/achievements/index.blade.php):
/// certificates and rewards.
class AchievementsScreen extends StatelessWidget {
  const AchievementsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () async => ((await context.api.get('achievements')) as Map)
            .cast<String, dynamic>(),
        builder: (context, data, reload) {
          final certificates = data.list('certificates');
          final rewards = data.list('rewards');
          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                context.tr('My achievements'),
                subtitle: context.tr(
                  'Your certificates and rewards. Points and badges are on the Points page.',
                ),
              ),
              const SizedBox(height: 14),
              LinkText(
                context.tr('Points'),
                onTap: () => context.go('/leaderboard'),
              ),
              const SizedBox(height: 32),
              SectionHeading(context.tr('Certificates')),
              const SizedBox(height: 14),
              if (certificates.isEmpty)
                MistBox(
                  context.tr(
                    'Finish a learning module, attend an event or enter a competition to earn your first certificate.',
                  ),
                  color: Colors.white,
                  center: true,
                ),
              for (final certificate in certificates)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: WebCard(
                    highlight: certificate.flag('valid'),
                    onTap: () =>
                        openLink(context, certificate.str('verify_url')),
                    child: Row(
                      children: [
                        Container(
                          width: 52,
                          height: 52,
                          decoration: BoxDecoration(
                            color: Brand.yellow,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: Brand.ink, width: 2),
                          ),
                          child: const Icon(Icons.workspace_premium, size: 30),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                certificate.str('title'),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 16,
                                ),
                              ),
                              Text(
                                certificate.str('subject'),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${certificate.str('number')} · ${context.date(certificate.date('issued_at'))}',
                                style: const TextStyle(
                                  fontFamily: 'IBMPlexMono',
                                  color: Brand.slate,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (!certificate.flag('valid'))
                          Tag(
                            context.tr('Withdrawn'),
                            color: const Color(0xFFFEE4E2),
                          )
                        else
                          const Icon(Icons.open_in_new, size: 18),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 28),
              SectionHeading(context.tr('Rewards')),
              const SizedBox(height: 14),
              if (rewards.isEmpty)
                MistBox(
                  context.tr('No rewards yet. Win a competition to get one!'),
                  color: Colors.white,
                ),
              for (final reward in rewards)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: WebCard(
                    child: Row(
                      children: [
                        const Icon(Icons.card_giftcard, size: 30),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                reward.str('reward'),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              if (reward.strOrNull('reason') != null)
                                Text(
                                  reward.str('reason'),
                                  style: const TextStyle(color: Brand.slate),
                                ),
                            ],
                          ),
                        ),
                        Tag(
                          labelOf(
                            context,
                            'reward_statuses',
                            reward.str('status'),
                          ),
                          color: Brand.mist,
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

/// Badge grid for the Points page.
class BadgeGrid extends StatelessWidget {
  const BadgeGrid({super.key, required this.badges});

  final List<Map<String, dynamic>> badges;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
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
              opacity: badge.flag('earned') ? 1 : 0.45,
              child: WebCard(
                highlight: badge.flag('earned'),
                padding: const EdgeInsets.all(8),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 24,
                      backgroundColor: badge.flag('earned')
                          ? Brand.yellow
                          : Brand.mist,
                      child: Icon(
                        badge.flag('earned')
                            ? Icons.military_tech
                            : Icons.lock_outline,
                        color: Brand.ink,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      badge.str('name'),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
