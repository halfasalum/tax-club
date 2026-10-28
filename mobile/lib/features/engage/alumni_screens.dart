import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
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
import '../community/community_screens.dart';

/// "Alumni directory" (livewire/member/alumni/directory.blade.php). Adults only.
class AlumniDirectoryScreen extends StatefulWidget {
  const AlumniDirectoryScreen({super.key});

  @override
  State<AlumniDirectoryScreen> createState() => _AlumniDirectoryScreenState();
}

class _AlumniDirectoryScreenState extends State<AlumniDirectoryScreen> {
  final _list = GlobalKey<PagedListState<Map<String, dynamic>>>();
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: PagedList<Map<String, dynamic>>(
        key: _list,
        padding: const EdgeInsets.fromLTRB(16, 28, 16, 40),
        separator: 12,
        load: (page) => context.api.paged(
          'alumni/directory',
          (row) => row,
          query: {'search': _search.text.trim(), 'page': page},
        ),
        header: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PageTitle(
              context.tr('Alumni directory'),
              subtitle: context.tr(
                'Alumni who chose to be listed. Ask to connect; contact details are shared only when they accept.',
              ),
            ),
            const SizedBox(height: 22),
            TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _list.currentState?.refresh(),
              decoration: InputDecoration(
                hintText: context.tr('Search by name, field, work or skill'),
                prefixIcon: const Icon(Icons.search),
              ),
            ),
            const SizedBox(height: 10),
          ],
        ),
        empty: MistBox(context.tr('No alumni match.'), color: Colors.white),
        itemBuilder: (context, person, _) => WebCard(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: Brand.yellow,
                child: Text(
                  initialsOf(person.str('name', '?')),
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: Brand.ink,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      person.str('name'),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 17,
                      ),
                    ),
                    if (person.strOrNull('occupation') != null)
                      Text(
                        [
                          person.str('occupation'),
                          person.str('organization'),
                        ].where((s) => s.isNotEmpty).join(' · '),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        person.str('institution'),
                        person.str('graduation_year'),
                      ].where((s) => s.isNotEmpty).join(', '),
                      style: const TextStyle(color: Brand.slate),
                    ),
                    if (person.flag('open_to_mentoring')) ...[
                      const SizedBox(height: 8),
                      Tag(context.tr('Open to mentoring')),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// "Mentorship" (livewire/member/mentorship/index.blade.php).
class MentorshipScreen extends StatelessWidget {
  const MentorshipScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('mentorships')),
        builder: (context, rows, reload) {
          final hasMentor = rows.any(
            (row) =>
                row.str('role') == 'mentee' && row.str('status') == 'active',
          );
          return MemberPage(
            onRefresh: reload,
            children: [
              PageTitle(
                context.tr('Mentorship'),
                subtitle: context.tr(
                  'Get guidance from Tax Club alumni and TRA staff on studies, careers and taxes.',
                ),
              ),
              const SizedBox(height: 22),
              MistBox(
                context.tr(
                  'Stay safe: meet online through approved channels or at your Tax Club, never alone in private. There is no private chat; record each session here. Members under 18 need a guardian\'s agreement, and their patron can follow the mentorship.',
                ),
                color: Brand.yellowWash,
              ),
              const SizedBox(height: 22),
              if (hasMentor)
                MistBox(
                  context.tr('You already have a mentor.'),
                  color: Colors.white,
                )
              else
                WebButton(
                  context.tr('Ask for a mentor'),
                  kind: BtnKind.brand,
                  icon: Icons.person_search_outlined,
                  onPressed: () async {
                    final sent = await context.push<bool>(
                      '/mentorship/request',
                    );
                    if (sent == true) reload();
                  },
                ),
              const SizedBox(height: 32),
              SectionHeading(context.tr('Your mentorships')),
              const SizedBox(height: 14),
              if (rows.isEmpty)
                MistBox(
                  context.tr('Your request is waiting for a match.'),
                  color: Colors.white,
                ),
              for (final mentorship in rows)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: WebCard(
                    highlight: mentorship.str('status') == 'active',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Tag(
                          context.tr(
                            mentorship.str('role') == 'mentor'
                                ? 'You mentor'
                                : 'Your mentor',
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          mentorship.str('role') == 'mentor'
                              ? mentorship.str('mentee')
                              : mentorship.str('mentor'),
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 18,
                          ),
                        ),
                        if (mentorship.date('started_at') != null)
                          Text(
                            context.tr('Active since :date', {
                              'date': context.date(
                                mentorship.date('started_at'),
                              ),
                            }),
                            style: const TextStyle(color: Brand.slate),
                          ),
                        if (mentorship.str('status') == 'active') ...[
                          const SizedBox(height: 14),
                          Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              WebButton(
                                context.tr('Record a session'),
                                expand: false,
                                onPressed: () async => context.push(
                                  '/mentorship/${mentorship.integer('id')}/session',
                                ),
                              ),
                              WebButton(
                                context.tr('Report a concern'),
                                kind: BtnKind.ghost,
                                expand: false,
                                onPressed: () => showReportSheet(
                                  context,
                                  type: 'mentorship',
                                  id: '${mentorship.integer('id')}',
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              Text(
                context.tr(
                  'Safety concerns go straight to a trained safeguarding officer and are kept confidential. If someone is in immediate danger, call 112 or tell a teacher or patron now.',
                ),
                style: const TextStyle(
                  color: Brand.slate,
                  fontSize: 14,
                  height: 1.5,
                ),
              ),
            ],
          );
        },
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
    return MemberScaffold(
      body: MemberPage(
        children: [
          PageTitle(
            context.tr('Ask for a mentor'),
            back: context.tr('Mentorship'),
            onBack: () => context.pop(),
          ),
          const SizedBox(height: 24),
          WebCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  context.tr('What do you want help with?'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final area in optionsOf(
                      context,
                      'mentor_areas',
                    ).entries)
                      FilterChip(
                        label: Text(area.value),
                        selected: _interests.contains(area.key),
                        onSelected: (on) => setState(
                          () => on
                              ? _interests.add(area.key)
                              : _interests.remove(area.key),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 20),
                WebField(
                  label: context.tr('Your goals'),
                  controller: _goals,
                  maxLines: 6,
                  hint: context.tr(
                    'For example: I want to study accounting and work at TRA.',
                  ),
                ),
                Text(
                  context.tr('How to meet'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final mode in {
                      'either': 'Online or at my Tax Club',
                      'online': 'Online only',
                      'in_person': 'At my Tax Club',
                    }.entries)
                      ChoiceChip(
                        label: Text(context.tr(mode.value)),
                        selected: _mode == mode.key,
                        onSelected: (_) => setState(() => _mode = mode.key),
                      ),
                  ],
                ),
                if (minor) ...[
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: _consent,
                    onChanged: (value) =>
                        setState(() => _consent = value ?? false),
                    title: Text(
                      context.tr(
                        'My parent or guardian agrees that I have a mentor',
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                WebButton(
                  context.tr('Send request'),
                  kind: BtnKind.brand,
                  onPressed: () => _send(minor),
                ),
              ],
            ),
          ),
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
    return MemberScaffold(
      body: MemberPage(
        children: [
          PageTitle(
            context.tr('Record a session'),
            back: context.tr('Mentorship'),
            onBack: () => context.pop(),
          ),
          const SizedBox(height: 24),
          WebCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  context.tr('Date'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 8),
                InkWell(
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: context,
                      initialDate: _heldOn,
                      firstDate: DateTime.now().subtract(
                        const Duration(days: 90),
                      ),
                      lastDate: DateTime.now(),
                    );
                    if (picked != null) setState(() => _heldOn = picked);
                  },
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      suffixIcon: Icon(Icons.calendar_today_outlined),
                    ),
                    child: Text(context.date(_heldOn)),
                  ),
                ),
                const SizedBox(height: 18),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final mode in optionsOf(
                      context,
                      'session_modes',
                    ).entries)
                      ChoiceChip(
                        label: Text(mode.value),
                        selected: _mode == mode.key,
                        onSelected: (_) => setState(() => _mode = mode.key),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                WebField(
                  label: context.tr('What did you talk about?'),
                  controller: _topic,
                ),
                WebField(
                  label: context.tr('Minutes'),
                  controller: _minutes,
                  keyboard: TextInputType.number,
                ),
                WebField(
                  label: context.tr('Notes (optional)'),
                  controller: _summary,
                  maxLines: 5,
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _milestone,
                  onChanged: (value) => setState(() => _milestone = value),
                  title: Text(context.tr('A goal was reached in this session')),
                ),
                const SizedBox(height: 12),
                WebButton(
                  context.tr('Save'),
                  onPressed: () async {
                    final result = await runAction(
                      context,
                      () => context.api.post(
                        'mentorships/${widget.id}/sessions',
                        {
                          'held_on': _heldOn.toIso8601String().substring(0, 10),
                          'mode': _mode,
                          'topic': _topic.text.trim(),
                          'summary': _summary.text.trim(),
                          'duration_minutes': int.tryParse(
                            _minutes.text.trim(),
                          ),
                          'milestone': _milestone,
                        },
                      ),
                      showServerMessage: true,
                    );
                    if (result != null && context.mounted) context.pop();
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
