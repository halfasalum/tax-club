import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';

import 'i18n.dart';
import 'session.dart';

/// A label from the server's translated lists, or a readable fallback.
String labelOf(BuildContext context, String group, String? key) {
  if (key == null || key.isEmpty) return '';
  final fromServer = context.read<Session>().label(group, key);
  if (fromServer != null) return fromServer;
  final local = _fallback[group]?[key] ?? humanize(key);
  return context.tr(local);
}

/// The server's list for a group, falling back to the app's own.
Map<String, String> optionsOf(BuildContext context, String group) {
  final fromServer = context.read<Session>().labels(group);
  if (fromServer.isNotEmpty) return fromServer;
  return (_fallback[group] ?? const {}).map((key, value) => MapEntry(key, context.tr(value)));
}

String humanize(String key) {
  final words = key.replaceAll('_', ' ').trim();
  return words.isEmpty ? words : words[0].toUpperCase() + words.substring(1);
}

/// Used when the server is older than the app's config endpoint.
const _fallback = <String, Map<String, String>>{
  'content_types': {'article': 'Article', 'video': 'Video', 'infographic': 'Infographic', 'document': 'Document'},
  'user_types': {'secondary': 'Secondary school', 'college': 'College', 'university': 'University'},
  'guardian_relationships': {'parent': 'Parent', 'guardian': 'Guardian', 'other': 'Other'},
  'feedback_categories': {
    'suggestion': 'Suggestion',
    'problem': 'Problem with the app or website',
    'content': 'Learning content',
    'event': 'An event',
    'account': 'My account',
    'concern': 'A concern',
    'other': 'Something else',
  },
  'report_categories': {
    'spam': 'Spam or advertising',
    'bullying': 'Bullying or harassment',
    'hate': 'Hate speech',
    'sexual': 'Sexual content',
    'violence': 'Violence or threats',
    'self_harm': 'Self-harm',
    'grooming': 'Grooming or an adult asking to meet',
    'contact_sharing': 'Sharing phone numbers or contacts',
    'personal_info': 'Personal information',
    'misinformation': 'Wrong tax information',
    'impersonation': 'Pretending to be someone else',
    'other': 'Something else',
  },
  'mentor_areas': {
    'tax_basics': 'Tax basics',
    'careers_in_tax': 'Careers in tax',
    'accounting': 'Accounting',
    'business_and_entrepreneurship': 'Business and entrepreneurship',
    'customs_and_trade': 'Customs and trade',
    'law': 'Law',
    'ict': 'ICT',
    'study_skills': 'Study skills',
    'leadership': 'Leadership',
  },
  'session_modes': {'in_person': 'In person', 'phone': 'Phone call', 'video': 'Video call', 'at_club': 'At the Tax Club'},
  'event_modes': {'in_person': 'In person', 'online': 'Online', 'hybrid': 'In person and online'},
  'event_status': {
    'published': 'Upcoming',
    'happening': 'Happening now',
    'ended': 'Ended',
    'cancelled': 'Cancelled',
    'postponed': 'Postponed',
    'scheduled': 'Scheduled',
    'results': 'Results out',
  },
  'registration_statuses': {
    'registered': 'Registered',
    'waitlisted': 'On the waitlist',
    'attended': 'Attended',
    'cancelled': 'Cancelled',
    'no_show': 'Did not attend',
  },
  'question_statuses': {
    'submitted': 'Received',
    'assigned': 'With a TRA expert',
    'returned': 'With a TRA expert',
    'in_review': 'Answer being checked',
    'answered': 'Answered',
    'declined': 'Closed without an answer',
  },
  'reward_statuses': {
    'pending_approval': 'Waiting for approval',
    'approved': 'Approved',
    'issued': 'On its way',
    'delivered': 'Delivered',
    'cancelled': 'Cancelled',
  },
};

/// Tone for a status pill.
String toneOf(String? status) => switch (status) {
      'registered' || 'attended' || 'answered' || 'delivered' || 'valid' || 'active' || 'resolved' || 'published' || 'happening' || 'submitted_entry' || 'scored' => 'good',
      'waitlisted' || 'in_review' || 'assigned' || 'returned' || 'pending' || 'issued' || 'approved' || 'in_progress' || 'pending_approval' => 'info',
      'cancelled' || 'declined' || 'revoked' || 'disqualified' || 'no_show' || 'closed' => 'bad',
      _ => 'neutral',
    };
