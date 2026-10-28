import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// "Events" (livewire/member/events/index.blade.php) with the Upcoming /
/// My events switch.
class EventsScreen extends StatefulWidget {
  const EventsScreen({super.key, this.mine = false});

  final bool mine;

  @override
  State<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends State<EventsScreen> {
  late bool _mine = widget.mine;
  String? _type;

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<List<Map<String, dynamic>>>(
        key: ValueKey('$_mine-$_type'),
        load: () async => listOf(
          await context.api.get(
            _mine ? 'events/mine' : 'events',
            query: {'type': _type},
          ),
        ),
        builder: (context, events, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              context.tr('Events'),
              subtitle: context.tr(
                'Workshops, competitions and campaigns for Tax Club members. Bring your membership card to check in.',
              ),
            ),
            const SizedBox(height: 22),
            Align(
              alignment: Alignment.centerLeft,
              child: Container(
                padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Brand.line, width: 2),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _Segment(
                      label: context.tr('Upcoming'),
                      selected: !_mine,
                      onTap: () => setState(() => _mine = false),
                    ),
                    _Segment(
                      label: context.tr('My events'),
                      selected: _mine,
                      onTap: () => setState(() => _mine = true),
                    ),
                  ],
                ),
              ),
            ),
            if (!_mine) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<String?>(
                initialValue: _type,
                items: [
                  DropdownMenuItem(
                    value: null,
                    child: Text(context.tr('All types')),
                  ),
                  for (final type in optionsOf(context, 'event_types').entries)
                    DropdownMenuItem(value: type.key, child: Text(type.value)),
                ],
                onChanged: (value) => setState(() => _type = value),
              ),
            ],
            const SizedBox(height: 20),
            if (events.isEmpty)
              MistBox(
                context.tr(
                  _mine
                      ? 'You have not registered for any events yet.'
                      : 'No upcoming events right now. Check back soon.',
                ),
                color: Colors.white,
                center: true,
              ),
            for (final event in events)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: EventCard(event: event),
              ),
          ],
        ),
      ),
    );
  }
}

class _Segment extends StatelessWidget {
  const _Segment({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? Brand.ink : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Text(
            label,
            style: TextStyle(
              fontWeight: FontWeight.w800,
              color: selected ? Brand.yellow : Brand.charcoal,
            ),
          ),
        ),
      ),
    );
  }
}

/// Event row with registration and capacity tags.
class EventCard extends StatelessWidget {
  const EventCard({super.key, required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final registration = event.obj('my_registration');
    final placesLeft = event.intOrNull('places_left');
    final status = event.str('status');
    return EventRowView(
      title: event.str('title'),
      startsAt: event.date('starts_at'),
      location: event.str('mode') == 'online'
          ? context.tr('Online')
          : event.strOrNull('location'),
      onTap: () => context.push('/events/${event.str('event_id')}'),
      trailing: Wrap(
        spacing: 6,
        runSpacing: 6,
        children: [
          Tag(labelOf(context, 'event_types', event.str('type'))),
          if (status == 'postponed')
            Tag(context.tr('Postponed'), color: const Color(0xFFFDE68A)),
          if (status == 'cancelled')
            Tag(
              labelOf(context, 'event_status', 'cancelled'),
              color: const Color(0xFFFEE4E2),
            ),
          if (registration != null)
            Tag(
              labelOf(
                context,
                'registration_statuses',
                registration.str('status'),
              ),
              dark: true,
            ),
          if (registration == null && placesLeft == 0)
            Tag(
              context.tr(
                event.flag('waitlist_enabled')
                    ? 'Full · waitlist open'
                    : 'Full',
              ),
              color: Brand.mist,
            ),
        ],
      ),
    );
  }
}

/// Kept for old links to /events/mine.
class MyEventsScreen extends StatelessWidget {
  const MyEventsScreen({super.key});

  @override
  Widget build(BuildContext context) => const EventsScreen(mine: true);
}

/// One event (livewire/member/events/show.blade.php).
class EventScreen extends StatefulWidget {
  const EventScreen({super.key, required this.id});

  final String id;

  @override
  State<EventScreen> createState() => _EventScreenState();
}

class _EventScreenState extends State<EventScreen> {
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();

  Future<void> _register(Map<String, dynamic> event) async {
    String? answer;
    final question = event.strOrNull('registration_question');
    if (question != null) {
      answer = await _ask(question);
      if (answer == null) return;
    }
    if (!mounted) return;
    final result = await runAction(
      context,
      () =>
          context.api.post('events/${widget.id}/register', {'answer': ?answer}),
      showServerMessage: true,
    );
    if (result != null) _loader.currentState?.reload();
  }

  Future<void> _cancel() async {
    if (!await confirm(
      context,
      context.tr(
        'Cancel your registration? Your place will go to someone on the waitlist.',
      ),
      action: context.tr('Cancel my registration'),
      danger: true,
    )) {
      return;
    }
    if (!mounted) return;
    final result = await runAction(
      context,
      () => context.api.delete('events/${widget.id}/register'),
      showServerMessage: true,
    );
    if (result != null) _loader.currentState?.reload();
  }

  Future<String?> _ask(String question) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          question,
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        content: TextField(
          controller: controller,
          maxLines: 3,
          autofocus: true,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(context.tr('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: Text(context.tr('Register')),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: () async =>
            ((await context.api.get('events/${widget.id}')) as Map)
                .cast<String, dynamic>(),
        builder: (context, event, reload) {
          final registration = event.obj('my_registration');
          final status = registration?.str('status');
          final problem = event.strOrNull('registration_problem');
          final placesLeft = event.intOrNull('places_left');
          final eventStatus = event.str('status');
          final ended = eventStatus == 'ended' || eventStatus == 'cancelled';
          final starts = event.date('starts_at');
          final ends = event.date('ends_at');

          return MemberPage(
            onRefresh: reload,
            children: [
              LinkText(
                context.tr('Events'),
                onTap: () => context.go('/events'),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                children: [
                  Tag(labelOf(context, 'event_types', event.str('type'))),
                  Tag(
                    labelOf(context, 'event_modes', event.str('mode')),
                    color: Colors.white,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                event.str('title'),
                style: const TextStyle(
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                ),
              ),
              if (eventStatus == 'cancelled') ...[
                const SizedBox(height: 14),
                MistBox(
                  context.tr('This event was cancelled.'),
                  color: const Color(0xFFFEE4E2),
                ),
              ],
              if (eventStatus == 'postponed') ...[
                const SizedBox(height: 14),
                MistBox(
                  context.tr(
                    'This event was postponed. We will share the new date.',
                  ),
                  color: Brand.yellowWash,
                ),
              ],
              const SizedBox(height: 20),
              if (starts != null)
                FactBox(
                  label: context.tr('When'),
                  value:
                      '${context.date(starts, pattern: 'EEEE, d MMMM y, HH:mm')}${ends == null ? '' : ' – ${context.time(ends)}'}',
                ),
              const SizedBox(height: 12),
              FactBox(
                label: context.tr('Where'),
                value: event.str('mode') == 'online'
                    ? context.tr('Online')
                    : [
                            event.str('location'),
                            event.str('region'),
                          ].where((s) => s.isNotEmpty).join(', ') +
                          (event.str('mode') == 'hybrid'
                              ? ' ${context.tr('or online')}'
                              : ''),
              ),
              const SizedBox(height: 20),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: registration != null ? Brand.yellowWash : Colors.white,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: registration != null ? Brand.ink : Brand.line,
                    width: 2,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr(
                        registration != null
                            ? 'Your registration'
                            : 'Take part',
                      ),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Brand.slate,
                      ),
                    ),
                    const SizedBox(height: 6),
                    if (registration != null) ...[
                      Text(
                        labelOf(context, 'registration_statuses', status),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (status == 'waitlisted' &&
                          registration.intOrNull('waitlist_position') != null)
                        Text(
                          context.tr(
                            'You are number :position on the waitlist.',
                            {
                              'position': registration.integer(
                                'waitlist_position',
                              ),
                            },
                          ),
                        ),
                      if (registration.date('checked_in_at') != null)
                        Text(
                          context.tr(
                            'Checked in :time. Thank you for coming!',
                            {
                              'time': context.dateTime(
                                registration.date('checked_in_at'),
                              ),
                            },
                          ),
                        )
                      else if (status == 'registered') ...[
                        const SizedBox(height: 6),
                        Text(
                          context.tr(
                            'Show your membership card at the door. The QR code checks you in.',
                          ),
                          style: const TextStyle(height: 1.5),
                        ),
                        const SizedBox(height: 14),
                        WebButton(
                          context.tr('Open my card'),
                          expand: false,
                          onPressed: () async => context.go('/card'),
                        ),
                      ],
                      if (!ended &&
                          (status == 'registered' ||
                              status == 'waitlisted')) ...[
                        const SizedBox(height: 12),
                        WebButton(
                          context.tr('Cancel my registration'),
                          kind: BtnKind.ghost,
                          expand: false,
                          onPressed: _cancel,
                        ),
                      ],
                    ] else if (!ended &&
                        event.flag('registration_required')) ...[
                      if (placesLeft != null)
                        Text(
                          placesLeft == 0
                              ? context.tr('Full. You can join the waitlist.')
                              : context.trPlural(
                                  ':count place left|:count places left',
                                  placesLeft,
                                ),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      if (event.date('registration_closes_at') != null)
                        Text(
                          context.tr('Registration closes :date', {
                            'date': context.dateTime(
                              event.date('registration_closes_at'),
                            ),
                          }),
                          style: const TextStyle(color: Brand.slate),
                        ),
                      const SizedBox(height: 14),
                      if (problem == null)
                        WebButton(
                          context.tr(
                            placesLeft == 0 ? 'Join the waitlist' : 'Register',
                          ),
                          kind: BtnKind.brand,
                          onPressed: () => _register(event),
                        )
                      else
                        Text(
                          problem,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                    ],
                    if (event.strOrNull('online_url') != null) ...[
                      const SizedBox(height: 12),
                      WebButton(
                        context.tr('Join online'),
                        icon: Icons.videocam_outlined,
                        onPressed: () =>
                            openLink(context, event.str('online_url')),
                      ),
                    ],
                  ],
                ),
              ),
              if (event.strOrNull('description') != null ||
                  event.strOrNull('objective') != null) ...[
                const SizedBox(height: 28),
                if (event.strOrNull('objective') != null) ...[
                  Paragraphs(event.str('objective')),
                  const SizedBox(height: 12),
                ],
                Paragraphs(event.strOrNull('description')),
              ],
              const SizedBox(height: 28),
              SectionHeading(context.tr('Updates and materials')),
              const SizedBox(height: 14),
              if (event.list('updates').isEmpty)
                MistBox(context.tr('No updates yet.'), color: Colors.white),
              for (final update in event.list('updates'))
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: WebCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Tag(
                              context.tr(
                                update.str('kind') == 'material'
                                    ? 'Material'
                                    : 'Update',
                              ),
                            ),
                            const SizedBox(width: 10),
                            Text(
                              context.ago(update.date('posted_at')),
                              style: const TextStyle(
                                color: Brand.slate,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          update.str('title'),
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 17,
                          ),
                        ),
                        if (update.strOrNull('body') != null) ...[
                          const SizedBox(height: 6),
                          Text(
                            update.str('body'),
                            style: const TextStyle(height: 1.5),
                          ),
                        ],
                        if (update.strOrNull('file_url') != null ||
                            update.strOrNull('link_url') != null) ...[
                          const SizedBox(height: 10),
                          LinkText(
                            context.tr('Open link'),
                            onTap: () => openLink(
                              context,
                              update.strOrNull('file_url') ??
                                  update.str('link_url'),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 16),
              LinkText(
                context.tr('Share feedback about this event'),
                onTap: () => context.push('/feedback/new'),
              ),
            ],
          );
        },
      ),
    );
  }
}
