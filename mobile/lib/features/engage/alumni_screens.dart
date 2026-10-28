import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../community/community_screens.dart';

/// Alumni who chose to be listed. Adults only.
class AlumniDirectoryScreen extends StatefulWidget {
  const AlumniDirectoryScreen({super.key});

  @override
  State<AlumniDirectoryScreen> createState() => _AlumniDirectoryScreenState();
}

class _AlumniDirectoryScreenState extends State<AlumniDirectoryScreen> {
  final _list = GlobalKey<PagedListState<Map<String, dynamic>>>();
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Alumni network'))),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: TextField(
            decoration: InputDecoration(hintText: context.tr('Search by name, field or organisation'), prefixIcon: const Icon(Icons.search), isDense: true),
            onChanged: (value) {
              _debounce?.cancel();
              _debounce = Timer(const Duration(milliseconds: 450), () {
                _search = value.trim();
                _list.currentState?.refresh();
              });
            },
          ),
        ),
        Expanded(
          child: PagedList<Map<String, dynamic>>(
            key: _list,
            load: (page) => context.api.paged('alumni/directory', (row) => row, query: {'search': _search, 'page': page}),
            empty: EmptyPanel(message: context.tr('No alumni found.'), icon: Icons.groups_outlined),
            itemBuilder: (context, person, _) => Tile(
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                CircleAvatar(backgroundColor: Brand.yellow, foregroundColor: Brand.ink, child: Text(person.str('name', '?').characters.first)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(person.str('name'), style: const TextStyle(fontWeight: FontWeight.w700)),
                    if (person.strOrNull('occupation') != null)
                      Text([person.str('occupation'), person.str('organization')].where((s) => s.isNotEmpty).join(' · ')),
                    InfoRow(icon: Icons.school_outlined, text: [person.str('institution'), person.str('graduation_year')].where((s) => s.isNotEmpty).join(', ')),
                    if (person.flag('open_to_mentoring')) ...[const SizedBox(height: 6), Pill.tone(context.tr('Open to mentoring'), 'good')],
                  ]),
                ),
              ]),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Text(context.tr('To connect with an alumnus, use the Alumni page on the website. Contact details are shared only when both agree.'),
              style: const TextStyle(color: Brand.slate, fontSize: 12), textAlign: TextAlign.center),
        ),
      ]),
    );
  }
}

class MentorshipScreen extends StatelessWidget {
  const MentorshipScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final loader = GlobalKey<LoaderState<List<Map<String, dynamic>>>>();
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Mentorship'))),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: Brand.ink,
        foregroundColor: Colors.white,
        onPressed: () async {
          final sent = await context.push<bool>('/mentorship/request');
          if (sent == true) loader.currentState?.reload();
        },
        icon: const Icon(Icons.person_search_outlined),
        label: Text(context.tr('Ask for a mentor')),
      ),
      body: Loader<List<Map<String, dynamic>>>(
        key: loader,
        load: () async => listOf(await context.api.get('mentorships')),
        isEmpty: (rows) => rows.isEmpty,
        empty: EmptyPanel(
          message: context.tr('A mentor is a Tax Club alumnus or TRA officer who helps you with studies and careers. Ask for one and a coordinator will match you.'),
          icon: Icons.handshake_outlined,
        ),
        builder: (context, rows, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            itemCount: rows.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final mentorship = rows[index];
              final asMentor = mentorship.str('role') == 'mentor';
              final active = mentorship.str('status') == 'active';
              return Tile(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Pill(context.tr(asMentor ? 'You are the mentor' : 'Your mentor')),
                    const Spacer(),
                    Pill.tone(context.tr(humanize(mentorship.str('status'))), active ? 'good' : 'neutral'),
                  ]),
                  const SizedBox(height: 8),
                  Text(asMentor ? mentorship.str('mentee') : mentorship.str('mentor'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  if (mentorship.date('started_at') != null) Text(context.tr('Since :date', {'date': context.date(mentorship.date('started_at'))}), style: const TextStyle(color: Brand.slate)),
                  const SizedBox(height: 10),
                  if (active)
                    Row(children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => context.push('/mentorship/${mentorship.integer('id')}/session'),
                          icon: const Icon(Icons.edit_note),
                          label: Text(context.tr('Record a session')),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        tooltip: context.tr('Report a concern'),
                        onPressed: () => showReportSheet(context, type: 'mentorship', id: '${mentorship.integer('id')}'),
                        icon: const Icon(Icons.flag_outlined),
                      ),
                    ]),
                ]),
              );
            },
          ),
        ),
      ),
    );
  }
}

class MentorRequestScreen extends StatefulWidget {
  const MentorRequestScreen({super.key});

  @override
  State<MentorRequestScreen> createState() => _MentorRequestScreenState();
}

class _MentorRequestScreenState extends State<MentorRequestScreen> {
  final _goals = TextEditingController();
  final _interests = <String>{};
  String _mode = 'either';
  bool _consent = false;

  @override
  void dispose() {
    _goals.dispose();
    super.dispose();
  }

  Future<void> _send(bool minor) async {
    if (_interests.isEmpty) {
      showMessage(context, context.tr('Choose at least one area.'), error: true);
      return;
    }
    if (_goals.text.trim().length < 20) {
      showMessage(context, context.tr('Tell us a little more about your goals (at least 20 characters).'), error: true);
      return;
    }
    final result = await runAction(
      context,
      () => context.api.post('mentorships/requests', {
        'interests': _interests.toList(),
        'goals': _goals.text.trim(),
        'mode': _mode,
        if (minor) 'guardian_consent': _consent,
      }),
      showServerMessage: true,
    );
    if (result != null && mounted) context.pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final minor = context.watch<Session>().isMinor;
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Ask for a mentor'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(context.tr('What would you like help with?'), style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Wrap(spacing: 8, runSpacing: 8, children: [
            for (final area in optionsOf(context, 'mentor_areas').entries)
              FilterChip(
                label: Text(area.value),
                selected: _interests.contains(area.key),
                onSelected: (on) => setState(() => on ? _interests.add(area.key) : _interests.remove(area.key)),
              ),
          ]),
          const SizedBox(height: 16),
          TextField(
            controller: _goals,
            minLines: 4,
            maxLines: 8,
            maxLength: 2000,
            decoration: InputDecoration(labelText: context.tr('Your goals'), hintText: context.tr('For example: I want to study accounting and learn about careers at TRA.'), alignLabelWithHint: true),
          ),
          const SizedBox(height: 8),
          Text(context.tr('How would you like to meet?'), style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Wrap(spacing: 8, children: [
            for (final mode in {'either': 'Either', 'online': 'Online', 'in_person': 'At my Tax Club'}.entries)
              ChoiceChip(label: Text(context.tr(mode.value)), selected: _mode == mode.key, onSelected: (_) => setState(() => _mode = mode.key)),
          ]),
          if (minor) ...[
            const SizedBox(height: 12),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _consent,
              onChanged: (value) => setState(() => _consent = value ?? false),
              title: Text(context.tr('My parent or guardian agrees. My patron will confirm this with them.')),
            ),
          ],
          const SizedBox(height: 16),
          BusyButton(label: context.tr('Send request'), icon: Icons.send, onPressed: () => _send(minor)),
          const SizedBox(height: 12),
          Text(context.tr('Mentors are checked by TRA. Sessions are recorded and there is no private chat.'), style: const TextStyle(color: Brand.slate, fontSize: 12)),
        ],
      ),
    );
  }
}

class LogSessionScreen extends StatefulWidget {
  const LogSessionScreen({super.key, required this.id});

  final String id;

  @override
  State<LogSessionScreen> createState() => _LogSessionScreenState();
}

class _LogSessionScreenState extends State<LogSessionScreen> {
  final _topic = TextEditingController();
  final _summary = TextEditingController();
  final _minutes = TextEditingController(text: '45');
  DateTime _heldOn = DateTime.now();
  String _mode = 'video';
  bool _milestone = false;

  @override
  void dispose() {
    _topic.dispose();
    _summary.dispose();
    _minutes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Record a session'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          InkWell(
            onTap: () async {
              final picked = await showDatePicker(context: context, initialDate: _heldOn, firstDate: DateTime.now().subtract(const Duration(days: 90)), lastDate: DateTime.now());
              if (picked != null) setState(() => _heldOn = picked);
            },
            child: InputDecorator(
              decoration: InputDecoration(labelText: context.tr('Date'), suffixIcon: const Icon(Icons.calendar_today_outlined)),
              child: Text(context.date(_heldOn)),
            ),
          ),
          const SizedBox(height: 16),
          Wrap(spacing: 8, runSpacing: 8, children: [
            for (final mode in optionsOf(context, 'session_modes').entries)
              ChoiceChip(label: Text(mode.value), selected: _mode == mode.key, onSelected: (_) => setState(() => _mode = mode.key)),
          ]),
          const SizedBox(height: 16),
          TextField(controller: _topic, maxLength: 200, decoration: InputDecoration(labelText: context.tr('What did you talk about?'))),
          TextField(controller: _minutes, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: context.tr('Minutes'))),
          const SizedBox(height: 16),
          TextField(controller: _summary, minLines: 3, maxLines: 6, maxLength: 2000, decoration: InputDecoration(labelText: context.tr('Notes (optional)'), alignLabelWithHint: true)),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _milestone,
            onChanged: (value) => setState(() => _milestone = value),
            title: Text(context.tr('A goal was reached in this session')),
          ),
          const SizedBox(height: 12),
          BusyButton(
            label: context.tr('Save'),
            onPressed: () async {
              final result = await runAction(
                context,
                () => context.api.post('mentorships/${widget.id}/sessions', {
                  'held_on': _heldOn.toIso8601String().substring(0, 10),
                  'mode': _mode,
                  'topic': _topic.text.trim(),
                  'summary': _summary.text.trim(),
                  'duration_minutes': int.tryParse(_minutes.text.trim()),
                  'milestone': _milestone,
                }),
                showServerMessage: true,
              );
              if (result != null && context.mounted) context.pop();
            },
          ),
        ],
      ),
    );
  }
}
