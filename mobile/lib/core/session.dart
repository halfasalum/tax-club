import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'i18n.dart';

enum SessionStatus { starting, signedOut, signedIn }

/// Result of the first sign-in step. Staff accounts get a challenge and
/// must enter the SMS code before they are signed in.
class LoginResult {
  LoginResult.done() : challenge = null, expiresInMinutes = null;
  LoginResult.needsCode(this.challenge, this.expiresInMinutes);

  final String? challenge;
  final int? expiresInMinutes;

  bool get needsCode => challenge != null;
}

/// Who is signed in, the chosen language and the server's settings.
class Session extends ChangeNotifier {
  Session({Api? api, FlutterSecureStorage? storage})
    : api = api ?? Api(),
      _storage = storage ?? const FlutterSecureStorage() {
    this.api.onUnauthorized = () {
      if (status == SessionStatus.signedIn) {
        _clear(expired: true);
      }
    };
  }

  static const _tokenKey = 'auth_token';
  static const _userKey = 'user';
  static const _localeKey = 'locale';
  static const _configKey = 'app_config';

  final Api api;
  final FlutterSecureStorage _storage;
  SharedPreferences? _prefs;

  SessionStatus status = SessionStatus.starting;
  Map<String, dynamic> user = const {};
  List<String> permissions = const [];
  Map<String, dynamic> config = const {};
  String locale = 'sw';
  bool expired = false;

  /// Unread inbox count shown on the bell; refreshed by the home screen.
  int unread = 0;

  bool get signedIn => status == SessionStatus.signedIn;
  String get firstName => user.str('full_name').split(' ').first;
  bool get isMinor => user.flag('is_minor');

  Future<void> start() async {
    _prefs = await SharedPreferences.getInstance();
    locale = _prefs!.getString(_localeKey) ?? _deviceLocale();
    api.locale = locale;

    final cachedConfig = _prefs!.getString(_configKey);
    if (cachedConfig != null) {
      config = (jsonDecode(cachedConfig) as Map).cast<String, dynamic>();
    }

    final token = await _readToken();
    final cachedUser = _prefs!.getString(_userKey);

    if (token != null) {
      api.token = token;
      user = cachedUser != null
          ? (jsonDecode(cachedUser) as Map).cast<String, dynamic>()
          : const {};
      status = SessionStatus.signedIn;
      refreshUser();
    } else {
      status = SessionStatus.signedOut;
    }

    notifyListeners();
    loadConfig();
  }

  Future<void> loadConfig() async {
    try {
      final data = await api.get('app/config');
      if (data is Map) {
        config = data.cast<String, dynamic>();
        await _prefs?.setString(_configKey, jsonEncode(config));
        notifyListeners();
      }
    } catch (_) {
      // Older servers have no config endpoint; the app uses its own labels.
    }
  }

  /// Translated label from the server's lists, e.g. label('event_types', 'workshop').
  String? label(String group, String? key) {
    if (key == null) return null;
    final labels = config.obj('labels')?.obj(group);
    final value = labels?[key];
    return value == null ? null : '$value';
  }

  Map<String, String> labels(String group) =>
      (config.obj('labels')?.obj(group) ?? const {}).map(
        (key, value) => MapEntry(key, '$value'),
      );

  Future<LoginResult> login(String identifier, String password) async {
    final envelope = await api.post('auth/login', {
      'identifier': identifier.trim(),
      'password': password,
    });
    final data = dataOf(envelope);

    if (data.flag('two_factor_required')) {
      return LoginResult.needsCode(
        data.str('challenge'),
        data.intOrNull('expires_in_minutes'),
      );
    }

    await _signIn(data);
    return LoginResult.done();
  }

  Future<void> verifyCode(String challenge, String code) async {
    final envelope = await api.post('auth/login/verify', {
      'challenge': challenge,
      'code': code.trim(),
    });
    await _signIn(dataOf(envelope));
  }

  Future<void> refreshUser() async {
    try {
      final profile = await api.get('profile');
      if (profile is Map) {
        user = {...user, ...profile.cast<String, dynamic>()};
        await _prefs?.setString(_userKey, jsonEncode(user));
        notifyListeners();
      }
    } on ApiException {
      // Offline: keep the cached profile.
    }
  }

  void updateUser(Map<String, dynamic> profile) {
    user = {...user, ...profile};
    _prefs?.setString(_userKey, jsonEncode(user));
    notifyListeners();
  }

  Future<void> setLocale(String value) async {
    if (!I18n.supported.contains(value) || value == locale) return;
    locale = value;
    api.locale = value;
    await _prefs?.setString(_localeKey, value);
    notifyListeners();
    loadConfig();

    if (signedIn) {
      try {
        final envelope = await api.put('profile', {
          'email': user.strOrNull('email'),
          'gender': user.strOrNull('gender'),
          'preferred_locale': value,
        });
        updateUser(dataOf(envelope));
      } catch (_) {
        // SMS language is also changed in the profile screen.
      }
    }
  }

  Future<void> logout() async {
    try {
      await api.post('auth/logout');
    } catch (_) {
      // Signing out locally is enough when offline.
    }
    await _clear();
  }

  /// After a password change every token is revoked.
  Future<void> signOutLocally() => _clear();

  void setUnread(int count) {
    if (count != unread) {
      unread = count;
      notifyListeners();
    }
  }

  Future<void> _signIn(Map<String, dynamic> data) async {
    final token = data.str('token');
    api.token = token;
    await _writeToken(token);
    user = data.obj('user') ?? const {};
    permissions = data.strings('permissions');
    expired = false;
    status = SessionStatus.signedIn;
    await _prefs?.setString(_userKey, jsonEncode(user));
    notifyListeners();
    refreshUser();
  }

  Future<void> _clear({bool expired = false}) async {
    api.token = null;
    await _deleteToken();
    await _prefs?.remove(_userKey);
    user = const {};
    permissions = const [];
    unread = 0;
    this.expired = expired;
    status = SessionStatus.signedOut;
    notifyListeners();
  }

  String _deviceLocale() {
    final code = PlatformDispatcher.instance.locale.languageCode;
    return code == 'en' ? 'en' : 'sw';
  }

  // Secure storage is not available in every test or web setting.
  Future<String?> _readToken() async {
    try {
      return await _storage.read(key: _tokenKey);
    } catch (_) {
      return _prefs?.getString(_tokenKey);
    }
  }

  Future<void> _writeToken(String token) async {
    try {
      await _storage.write(key: _tokenKey, value: token);
    } catch (_) {
      await _prefs?.setString(_tokenKey, token);
    }
  }

  Future<void> _deleteToken() async {
    try {
      await _storage.delete(key: _tokenKey);
    } catch (_) {}
    await _prefs?.remove(_tokenKey);
  }
}
