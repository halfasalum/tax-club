import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

class OpportunitiesScreen extends StatefulWidget {
  const OpportunitiesScreen({super.key});

  @override
  State<OpportunitiesScreen> createState() => _OpportunitiesScreenState();
}

class _OpportunitiesScreenState extends State<OpportunitiesScreen> {
  String _view = 'open';
  String? _type;
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final types = optionsOf(context, 'opportunity_types');

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr('Opportunities')),
          bottom: TabBar(
            labelColor: Brand.ink,
            indicatorColor: Brand.ink,
            tabs: [Tab(text: context.tr('Opportunities')), Tab(text: context.tr('Career resources'))],
          ),
        ),
        body: TabBarView(children: [
          Column(children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: TextField(
                decoration: InputDecoration(hintText: context.tr('Search opportunities'), prefixIcon: const Icon(Icons.search), isDense: true),
                onChanged: (value) {
                  _debounce?.cancel();
                  _debounce = Timer(const Duration(milliseconds: 450), () => setState(() => _search = value.trim()));
                },
              ),
            ),
            SizedBox(
              height: 52,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                children: [
                  for (final view in {'open': 'All open', 'closing_soon': 'Closing soon', 'saved': 'Saved'}.entries)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(label: Text(context.tr(view.value)), selected: _view == view.key, onSelected: (_) => setState(() => _view = view.key)),
                    ),
                  for (final type in types.entries)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: FilterChip(label: Text(type.value), selected: _type == type.key, onSelected: (on) => setState(() => _type = on ? type.key : null)),
                    ),
                ],
              ),
            ),
            Expanded(
              child: Loader<List<Map<String, dynamic>>>(
                key: ValueKey('$_view-$_type-$_search'),
                load: () async => listOf(await context.api.get('opportunities', query: {
                  'search': _search,
                  'type': _type,
                  if (_view == 'closing_soon') 'closing_soon': 1,
                  if (_view == 'saved') 'saved': 1,
                })),
                isEmpty: (items) => items.isEmpty,
                empty: EmptyPanel(message: context.tr(_view == 'saved' ? 'You have not saved any opportunity yet.' : 'No opportunities match.'), icon: Icons.work_off_outlined),
                builder: (context, items, reload) => RefreshIndicator(
                  onRefresh: reload,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (context, index) => _OpportunityCard(item: items[index]),
                  ),
                ),
              ),
            ),
          ]),
          Loader<List<Map<String, dynamic>>>(
            load: () async => listOf(await context.api.get('opportunities/career-resources')),
            isEmpty: (items) => items.isEmpty,
            builder: (context, items, reload) => ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) => Tile(
                onTap: () => context.push('/lesson/${items[index].str('content_id')}'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(items[index].str('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                  if (items[index].strOrNull('summary') != null) Text(items[index].str('summary'), style: const TextStyle(color: Brand.slate)),
                ]),
              ),
            ),
          ),
        ]),
      ),
    );
  }
}

class _OpportunityCard extends StatelessWidget {
  const _OpportunityCard({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    return Tile(
      onTap: () => context.push('/opportunities/${item.str('opportunity_id')}'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Pill(labelOf(context, 'opportunity_types', item.str('type'))),
          const SizedBox(width: 6),
          if (item.flag('closing_soon')) Pill.tone(context.tr('Closing soon'), 'warn'),
          if (!item.flag('is_open')) Pill.tone(context.tr('Closed'), 'bad'),
          const Spacer(),
          if (item.flag('saved')) const Icon(Icons.bookmark, color: Brand.ink),
        ]),
        const SizedBox(height: 8),
        Text(item.str('title'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        InfoRow(icon: Icons.business_outlined, text: item.str('organization')),
        InfoRow(icon: Icons.place_outlined, text: [item.str('location'), item.str('region')].where((s) => s.isNotEmpty).join(', ')),
        if (item.date('deadline') != null) InfoRow(icon: Icons.event_outlined, text: context.tr('Closes :date', {'date': context.date(item.date('deadline'))})),
      ]),
    );
  }
}

class OpportunityScreen extends StatefulWidget {
  const OpportunityScreen({super.key, required this.id});

  final String id;

  @override
  State<OpportunityScreen> createState() => _OpportunityScreenState();
}

class _OpportunityScreenState extends State<OpportunityScreen> {
  bool? _saved;

  Future<void> _toggleSave(bool saved) async {
    final result = await runAction(context, () => saved ? context.api.delete('opportunities/${widget.id}/save') : context.api.post('opportunities/${widget.id}/save'));
    if (result != null && mounted) {
      setState(() => _saved = dataOf(result).flag('saved'));
      showMessage(context, context.tr(_saved! ? 'Saved. We will remind you before it closes.' : 'Removed from saved.'));
    }
  }

  Future<void> _apply() async {
    final result = await runAction(context, () => context.api.post('opportunities/${widget.id}/apply'));
    if (result == null || !mounted) return;
    final target = dataOf(result).str('apply_to');
    if (target.contains('@') && !target.startsWith('http')) {
      await openLink(context, 'mailto:$target');
    } else {
      await openLink(context, target);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Opportunity'))),
      body: Loader<Map<String, dynamic>>(
        load: () async => ((await context.api.get('opportunities/${widget.id}')) as Map).cast<String, dynamic>(),
        builder: (context, item, reload) {
          final saved = _saved ?? item.flag('saved');
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Wrap(spacing: 6, children: [
                Pill(labelOf(context, 'opportunity_types', item.str('type'))),
                if (item.flag('closing_soon')) Pill.tone(context.tr('Closing soon'), 'warn'),
                if (!item.flag('is_open')) Pill.tone(context.tr('Closed'), 'bad'),
              ]),
              const SizedBox(height: 10),
              Text(item.str('title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              InfoRow(icon: Icons.business_outlined, text: item.str('organization')),
              InfoRow(icon: Icons.place_outlined, text: [item.str('location'), item.str('region')].where((s) => s.isNotEmpty).join(', ')),
              if (item.date('deadline') != null) InfoRow(icon: Icons.event_outlined, text: context.tr('Closes :date', {'date': context.date(item.date('deadline'))})),
              InfoRow(icon: Icons.people_outline, text: item.str('audience')),
              const SizedBox(height: 16),
              if (item.flag('is_open'))
                BusyButton(label: context.tr('Apply'), icon: Icons.open_in_new, onPressed: _apply),
              const SizedBox(height: 10),
              BusyButton(
                label: context.tr(saved ? 'Saved' : 'Save for later'),
                icon: saved ? Icons.bookmark : Icons.bookmark_outline,
                outlined: true,
                onPressed: () => _toggleSave(saved),
              ),
              const SizedBox(height: 20),
              Paragraphs(item.strOrNull('description')),
              if (item.strOrNull('eligibility') != null) ...[
                const SizedBox(height: 16),
                SectionTitle(context.tr('Who can apply')),
                Paragraphs(item.str('eligibility')),
              ],
              if (item.strOrNull('apply_instructions') != null) ...[
                const SizedBox(height: 16),
                SectionTitle(context.tr('How to apply')),
                Paragraphs(item.str('apply_instructions')),
              ],
              if (item.strOrNull('attachment_url') != null) ...[
                const SizedBox(height: 16),
                BusyButton(label: context.tr('Open the attachment'), icon: Icons.attach_file, outlined: true, onPressed: () => openLink(context, item.str('attachment_url'))),
              ],
              const SizedBox(height: 24),
            ],
          );
        },
      ),
    );
  }
}
