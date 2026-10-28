import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../home/home_screen.dart';

class EventsScreen extends StatefulWidget {
  const EventsScreen({super.key});

  @override
  State<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends State<EventsScreen> {
  String? _type;

  @override
  Widget build(BuildContext context) {
    final types = optionsOf(context, 'event_types');

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Events')),
        actions: [
          TextButton.icon(onPressed: () => context.push('/events/mine'), icon: const Icon(Icons.bookmark_outline), label: Text(context.tr('My events'))),
        ],
      ),
      body: Column(children: [
        if (types.isNotEmpty)
          SizedBox(
            height: 56,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
              children: [
                ChoiceChip(label: Text(context.tr('All')), selected: _type == null, onSelected: (_) => setState(() => _type = null)),
                for (final type in types.entries)
                  Padding(
                    padding: const EdgeInsets.only(left: 8),
                    child: ChoiceChip(label: Text(type.value), selected: _type == type.key, onSelected: (_) => setState(() => _type = type.key)),
                  ),
              ],
            ),
          ),
        Expanded(
          child: Loader<List<Map<String, dynamic>>>(
            key: ValueKey(_type),
            load: () async => listOf(await context.api.get('events', query: {'type': _type})),
            isEmpty: (events) => events.isEmpty,
            empty: EmptyPanel(message: context.tr('No upcoming events for you right now.'), icon: Icons.event_busy),
            builder: (context, events, reload) => RefreshIndicator(
              onRefresh: reload,
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: events.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (context, index) => EventCard(event: events[index]),
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

class EventCard extends StatelessWidget {
  const EventCard({super.key, required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final registration = event.obj('my_registration');
    return Tile(
      onTap: () => context.push('/events/${event.str('event_id')}'),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        DateBox(date: event.date('starts_at')),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Wrap(spacing: 6, runSpacing: 4, children: [
              Pill(labelOf(context, 'event_types', event.str('type'))),
              if (event.str('status') == 'happening') Pill.tone(labelOf(context, 'event_status', 'happening'), 'good'),
              if (event.str('status') == 'cancelled') Pill.tone(labelOf(context, 'event_status', 'cancelled'), 'bad'),
              if (registration != null) Pill.tone(labelOf(context, 'registration_statuses', registration.str('status')), toneOf(registration.str('status'))),
            ]),
            const SizedBox(height: 6),
            Text(event.str('title'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            InfoRow(icon: Icons.schedule, text: context.dateTime(event.date('starts_at'))),
            InfoRow(icon: event.str('mode') == 'online' ? Icons.videocam_outlined : Icons.place_outlined, text: event.str('mode') == 'online' ? labelOf(context, 'event_modes', 'online') : event.str('location')),
          ]),
        ),
      ]),
    );
  }
}

class MyEventsScreen extends StatelessWidget {
  const MyEventsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My events'))),
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('events/mine')),
        isEmpty: (events) => events.isEmpty,
        empty: EmptyPanel(message: context.tr('You have not registered for any event yet.'), icon: Icons.event_note),
        builder: (context, events, reload) => RefreshIndicator(
          onRefresh: reload,
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: events.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) => EventCard(event: events[index]),
          ),
        ),
      ),
    );
  }
}

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
    final result = await runAction(context, () => context.api.post('events/${widget.id}/register', {'answer': ?answer}), showServerMessage: true);
    if (result != null) _loader.currentState?.reload();
  }

  Future<void> _cancel() async {
    if (!await confirm(context, context.tr('Cancel your registration for this event?'), action: context.tr('Cancel registration'), danger: true)) return;
    if (!mounted) return;
    final result = await runAction(context, () => context.api.delete('events/${widget.id}/register'), showServerMessage: true);
    if (result != null) _loader.currentState?.reload();
  }

  Future<String?> _ask(String question) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('One question first')),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(question),
          const SizedBox(height: 12),
          TextField(controller: controller, maxLines: 3, autofocus: true),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, controller.text.trim()), child: Text(context.tr('Register'))),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Event'))),
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: () async => ((await context.api.get('events/${widget.id}')) as Map).cast<String, dynamic>(),
        builder: (context, event, reload) {
          final registration = event.obj('my_registration');
          final status = registration?.str('status');
          final problem = event.strOrNull('registration_problem');
          final placesLeft = event.intOrNull('places_left');
          final ended = event.str('status') == 'ended' || event.str('status') == 'cancelled';

          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Wrap(spacing: 6, runSpacing: 6, children: [
                  Pill(labelOf(context, 'event_types', event.str('type'))),
                  Pill(labelOf(context, 'event_modes', event.str('mode'))),
                  if (event.str('status') != 'published') Pill.tone(labelOf(context, 'event_status', event.str('status')), toneOf(event.str('status'))),
                ]),
                const SizedBox(height: 10),
                Text(event.str('title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                InfoRow(icon: Icons.schedule, text: '${context.dateTime(event.date('starts_at'))}${event.date('ends_at') != null ? ' – ${context.time(event.date('ends_at'))}' : ''}'),
                InfoRow(icon: Icons.place_outlined, text: [event.str('location'), event.str('region')].where((s) => s.isNotEmpty).join(', ')),
                InfoRow(icon: Icons.groups_outlined, text: event.str('organizer')),
                InfoRow(icon: Icons.people_outline, text: event.str('audience')),
                if (placesLeft != null) InfoRow(icon: Icons.event_seat_outlined, text: context.trPlural(':count place left|:count places left', placesLeft)),
                if (event.date('registration_closes_at') != null && registration == null && !ended)
                  InfoRow(icon: Icons.timer_outlined, text: context.tr('Registration closes :date', {'date': context.dateTime(event.date('registration_closes_at'))})),
                const SizedBox(height: 16),
                if (registration != null)
                  Tile(
                    color: Brand.yellowWash,
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(labelOf(context, 'registration_statuses', status), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                      if (status == 'waitlisted' && registration.intOrNull('waitlist_position') != null)
                        Text(context.tr('You are number :position on the waitlist.', {'position': registration.integer('waitlist_position')})),
                      if (status == 'registered') Text(context.tr('Show your membership card QR code at the entrance.')),
                      if (registration.date('checked_in_at') != null) Text(context.tr('Checked in at :time', {'time': context.dateTime(registration.date('checked_in_at'))})),
                    ]),
                  ),
                const SizedBox(height: 12),
                if (event.strOrNull('online_url') != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: BusyButton(label: context.tr('Join online'), icon: Icons.videocam_outlined, onPressed: () => openLink(context, event.str('online_url'))),
                  ),
                if (!ended && registration == null && event.flag('registration_required'))
                  problem == null
                      ? BusyButton(label: context.tr('Register'), icon: Icons.how_to_reg, onPressed: () => _register(event))
                      : Tile(child: Text(problem)),
                if (!ended && (status == 'registered' || status == 'waitlisted'))
                  BusyButton(label: context.tr('Cancel registration'), outlined: true, onPressed: _cancel),
                if (event.strOrNull('objective') != null) ...[
                  const SizedBox(height: 20),
                  SectionTitle(context.tr('Why attend')),
                  Paragraphs(event.str('objective')),
                ],
                if (event.strOrNull('description') != null) ...[
                  const SizedBox(height: 12),
                  SectionTitle(context.tr('About this event')),
                  Paragraphs(event.str('description')),
                ],
                if (event.list('updates').isNotEmpty) ...[
                  const SizedBox(height: 12),
                  SectionTitle(context.tr('Updates and materials')),
                  for (final update in event.list('updates'))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Tile(
                        onTap: (update.strOrNull('file_url') ?? update.strOrNull('link_url')) == null
                            ? null
                            : () => openLink(context, update.strOrNull('file_url') ?? update.str('link_url')),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(update.str('title'), style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text(context.ago(update.date('posted_at')), style: const TextStyle(color: Brand.slate, fontSize: 12)),
                          if (update.strOrNull('body') != null) ...[const SizedBox(height: 6), Text(update.str('body'))],
                          if (update.strOrNull('file_url') != null || update.strOrNull('link_url') != null)
                            Padding(
                              padding: const EdgeInsets.only(top: 6),
                              child: Text(context.tr('Open'), style: const TextStyle(fontWeight: FontWeight.w700, decoration: TextDecoration.underline)),
                            ),
                        ]),
                      ),
                    ),
                ],
                const SizedBox(height: 24),
              ],
            ),
          );
        },
      ),
    );
  }
}
