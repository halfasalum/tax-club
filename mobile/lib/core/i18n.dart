import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';

/// The app is written in English; Kiswahili comes from assets/i18n/sw.json,
/// keyed by the English text (the same approach as the website).
class I18n {
  static const supported = ['sw', 'en'];
  static Map<String, String> _sw = const {};

  static Future<void> load() async {
    final raw = await rootBundle.loadString('assets/i18n/sw.json');
    _sw = (jsonDecode(raw) as Map).map(
      (key, value) => MapEntry('$key', '$value'),
    );
    await initializeDateFormatting('sw');
    await initializeDateFormatting('en');
  }

  /// Replaces the Kiswahili strings, for tests.
  @visibleForTesting
  static void use(Map<String, String> sw) => _sw = sw;

  static String translate(
    String locale,
    String text, [
    Map<String, Object?> replace = const {},
  ]) {
    var result = locale == 'sw' ? (_sw[text] ?? text) : text;

    if (replace.isNotEmpty) {
      final keys = replace.keys.toList()
        ..sort((a, b) => b.length.compareTo(a.length));
      for (final key in keys) {
        result = result.replaceAll(':$key', '${replace[key] ?? ''}');
      }
    }

    return result;
  }

  /// Picks the right part of "one|many", or of Laravel's ranged form
  /// "{0} none|{1} one|[2,*] many".
  static String plural(
    String locale,
    String text,
    int count, [
    Map<String, Object?> replace = const {},
  ]) {
    final parts = translate(locale, text).split('|');
    var chosen = parts.length > 1 && count != 1 ? parts[1] : parts.first;

    final ranged = RegExp(
      r'^\s*(?:\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*(.*)$',
      dotAll: true,
    );
    if (parts.every((part) => ranged.hasMatch(part))) {
      for (final part in parts) {
        final match = ranged.firstMatch(part)!;
        final exact = int.tryParse(match.group(1) ?? '');
        final from = int.tryParse(match.group(2) ?? '');
        final to = match.group(3) == '*'
            ? null
            : int.tryParse(match.group(3) ?? '');
        if ((exact != null && exact == count) ||
            (from != null && count >= from && (to == null || count <= to))) {
          chosen = match.group(4)!;
          break;
        }
      }
    }

    final keys = {'count': count, ...replace};
    var result = chosen;
    for (final key
        in keys.keys.toList()..sort((a, b) => b.length.compareTo(a.length))) {
      result = result.replaceAll(':$key', '${keys[key] ?? ''}');
    }
    return result;
  }
}

extension Tr on BuildContext {
  /// Current language code; depends on Localizations so screens rebuild
  /// when the member switches language.
  String get lang {
    final code = Localizations.maybeLocaleOf(this)?.languageCode ?? 'sw';
    return I18n.supported.contains(code) ? code : 'sw';
  }

  String tr(String text, [Map<String, Object?> replace = const {}]) =>
      I18n.translate(lang, text, replace);

  String trPlural(
    String text,
    int count, [
    Map<String, Object?> replace = const {},
  ]) => I18n.plural(lang, text, count, replace);

  String date(DateTime? value, {String pattern = 'd MMM y'}) =>
      value == null ? '' : DateFormat(pattern, lang).format(value);

  String dateTime(DateTime? value) =>
      value == null ? '' : DateFormat('d MMM y, HH:mm', lang).format(value);

  String time(DateTime? value) =>
      value == null ? '' : DateFormat('HH:mm', lang).format(value);

  /// "3 hours ago" style, falling back to the date after a week.
  String ago(DateTime? value) {
    if (value == null) return '';
    final diff = DateTime.now().difference(value);
    if (diff.inMinutes < 1) return tr('Just now');
    if (diff.inMinutes < 60) {
      return trPlural(':count minute ago|:count minutes ago', diff.inMinutes);
    }
    if (diff.inHours < 24) {
      return trPlural(':count hour ago|:count hours ago', diff.inHours);
    }
    if (diff.inDays < 7) {
      return trPlural(':count day ago|:count days ago', diff.inDays);
    }
    return date(value);
  }

  String number(num value) => NumberFormat.decimalPattern(lang).format(value);
}
