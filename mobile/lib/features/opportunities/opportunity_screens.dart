import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// "Opportunities" (livewire/member/opportunities/index.blade.php).
class OpportunitiesScreen extends StatefulWidget {
  const OpportunitiesScreen({super.key});

  @override
  State<OpportunitiesScreen> createState() => _OpportunitiesScreenState();
}

class _OpportunitiesScreenState extends State<OpportunitiesScreen> {
  final _search = TextEditingController();
  String _view = 'open';
  String? _type;
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: ValueKey('$_view-$_type-$_query'),
        load: () async {
          final api = context.api;
          final results = await Future.wait([
            api.get(
              'opportunities',
              query: {
                'search': _query,
                'type': _type,
                if (_view == 'closing_soon') 'closing_soon': 1,
                if (_view == 'saved') 'saved': 1,
              },
            ),
            api
                .get('opportunities/career-resources')
                .catchError((_) => <dynamic>[]),
          ]);
          return {'items': results[0], 'resources': results[1]};
        },
        builder: (context, data, reload) {
          final items = data.list('items');
          final resources = data.list('resources');
          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                context.tr('Opportunities'),
                subtitle: context.tr(
                  'Internships, jobs, scholarships and training checked by TRA Tax Club. Save the ones you like and apply before the deadline.',
                ),
              ),
              const SizedBox(height: 22),
              TextField(
                controller: _search,
                textInputAction: TextInputAction.search,
                onSubmitted: (value) => setState(() => _query = value.trim()),
                decoration: InputDecoration(
                  hintText: context.tr('Search opportunities'),
                  prefixIcon: const Icon(Icons.search),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String?>(
                initialValue: _type,
                items: [
                  DropdownMenuItem(
                    value: null,
                    child: Text(context.tr('All types')),
                  ),
                  for (final type in optionsOf(
                    context,
                    'opportunity_types',
                  ).entries)
                    DropdownMenuItem(value: type.key, child: Text(type.value)),
                ],
                onChanged: (value) => setState(() => _type = value),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final view in {
                    'open': 'Open now',
                    'closing_soon': 'Closing soon',
                    'saved': 'Saved',
                  }.entries)
                    ChoiceChip(
                      label: Text(context.tr(view.value)),
                      selected: _view == view.key,
                      onSelected: (_) => setState(() => _view = view.key),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              if (items.isEmpty)
                MistBox(
                  context.tr(
                    _view == 'saved'
                        ? 'You have not saved any opportunities yet.'
                        : 'No open opportunities match. Check back soon.',
                  ),
                  color: Colors.white,
                  center: true,
                ),
              for (final item in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _OpportunityCard(item: item),
                ),
              if (resources.isNotEmpty) ...[
                const SizedBox(height: 28),
                SectionHeading(context.tr('Career guidance')),
                const SizedBox(height: 4),
                Text(
                  context.tr(
                    'Get ready to apply: CVs, interviews and careers in tax.',
                  ),
                  style: const TextStyle(color: Brand.slate, fontSize: 16),
                ),
                const SizedBox(height: 14),
                for (final lesson in resources)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: WebCard(
                      onTap: () =>
                          context.push('/lesson/${lesson.str('content_id')}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            lesson.str('title'),
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 17,
                            ),
                          ),
                          if (lesson.strOrNull('summary') != null) ...[
                            const SizedBox(height: 6),
                            Text(
                              lesson.str('summary'),
                              style: const TextStyle(color: Brand.slate),
                            ),
                          ],
                          const SizedBox(height: 10),
                          LinkText(
                            context.tr('Read'),
                            onTap: () => context.push(
                              '/lesson/${lesson.str('content_id')}',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _OpportunityCard extends StatelessWidget {
  const _OpportunityCard({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    return WebCard(
      highlight: item.flag('saved'),
      onTap: () => context.push('/opportunities/${item.str('opportunity_id')}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Tag(labelOf(context, 'opportunity_types', item.str('type'))),
              if (item.flag('closing_soon'))
                Tag(context.tr('Closing soon'), dark: true),
              if (!item.flag('is_open'))
                Tag(context.tr('Closed'), color: Brand.mist),
              if (item.flag('saved'))
                Text(
                  context.tr('Saved'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            item.str('title'),
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            item.str('organization'),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: Brand.slate,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            item.date('deadline') != null
                ? context.tr('Apply by :date', {
                    'date': context.date(
                      item.date('deadline'),
                      pattern: 'd MMMM y',
                    ),
                  })
                : context.tr('Open until filled'),
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
          ),
        ],
      ),
    );
  }
}

/// One opportunity (livewire/member/opportunities/show.blade.php).
class OpportunityScreen extends StatefulWidget {
  const OpportunityScreen({super.key, required this.id});

  final String id;

  @override
  State<OpportunityScreen> createState() => _OpportunityScreenState();
}

class _OpportunityScreenState extends State<OpportunityScreen> {
  bool? _saved;

  Future<void> _toggleSave(bool saved) async {
    final result = await runAction(
      context,
      () => saved
          ? context.api.delete('opportunities/${widget.id}/save')
          : context.api.post('opportunities/${widget.id}/save'),
    );
    if (result != null && mounted) {
      setState(() => _saved = dataOf(result).flag('saved'));
      if (_saved!) {
        showMessage(
          context,
          context.tr('We will remind you before it closes.'),
        );
      }
    }
  }

  Future<void> _apply() async {
    if (!await confirm(
      context,
      context.tr(
        'You will leave TRA Tax Club. Never pay anyone to apply for an opportunity.',
      ),
      action: context.tr('Apply now'),
    )) {
      return;
    }
    if (!mounted) return;
    final result = await runAction(
      context,
      () => context.api.post('opportunities/${widget.id}/apply'),
    );
    if (result == null || !mounted) return;
    final target = dataOf(result).str('apply_to');
    await openLink(
      context,
      target.contains('@') && !target.startsWith('http')
          ? 'mailto:$target'
          : target,
    );
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        load: () async =>
            ((await context.api.get('opportunities/${widget.id}')) as Map)
                .cast<String, dynamic>(),
        builder: (context, item, reload) {
          final saved = _saved ?? item.flag('saved');
          return MemberPage(
            onRefresh: reload,
            children: [
              LinkText(
                context.tr('Opportunities'),
                onTap: () => context.go('/opportunities'),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                children: [
                  Tag(labelOf(context, 'opportunity_types', item.str('type'))),
                  if (item.flag('closing_soon'))
                    Tag(context.tr('Closing soon'), dark: true),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                item.str('title'),
                style: const TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                item.str('organization'),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: Brand.slate,
                ),
              ),
              const SizedBox(height: 20),
              FactBox(
                label: context.tr('Deadline'),
                value: item.date('deadline') != null
                    ? context.date(item.date('deadline'), pattern: 'd MMMM y')
                    : context.tr('Open until filled'),
              ),
              const SizedBox(height: 12),
              FactBox(
                label: context.tr('Location'),
                value: [
                  item.str('location'),
                  item.str('region'),
                ].where((s) => s.isNotEmpty).join(', ').ifEmpty('—'),
              ),
              if (!item.flag('is_open')) ...[
                const SizedBox(height: 14),
                MistBox(
                  context.tr('This opportunity is closed to new applications.'),
                  color: Colors.white,
                ),
              ],
              const SizedBox(height: 20),
              if (item.flag('is_open')) ...[
                WebButton(
                  context.tr('Apply now'),
                  kind: BtnKind.brand,
                  icon: Icons.open_in_new,
                  onPressed: _apply,
                ),
                const SizedBox(height: 10),
              ],
              WebButton(
                context.tr(saved ? 'Saved' : 'Save for later'),
                kind: BtnKind.ghost,
                icon: saved ? Icons.bookmark : Icons.bookmark_outline,
                onPressed: () => _toggleSave(saved),
              ),
              const SizedBox(height: 28),
              Paragraphs(item.strOrNull('description')),
              if (item.strOrNull('eligibility') != null) ...[
                const SizedBox(height: 24),
                SectionHeading(context.tr('Who can apply'), size: 22),
                const SizedBox(height: 8),
                Paragraphs(item.str('eligibility')),
              ],
              if (item.strOrNull('apply_instructions') != null) ...[
                const SizedBox(height: 24),
                SectionHeading(context.tr('How to apply'), size: 22),
                const SizedBox(height: 8),
                Paragraphs(item.str('apply_instructions')),
              ],
              if (item.strOrNull('attachment_url') != null) ...[
                const SizedBox(height: 20),
                WebButton(
                  context.tr('Instructions'),
                  kind: BtnKind.ghost,
                  icon: Icons.attach_file,
                  onPressed: () =>
                      openLink(context, item.str('attachment_url')),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}
