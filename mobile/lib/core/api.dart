import 'package:dio/dio.dart';

/// Where the Tax Club API lives. Override at build time with
/// `--dart-define=API_BASE_URL=https://example.org`.
const String apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'https://club.flux.co.tz');

/// A failed API call, with the server's message and field errors.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors = const {}});

  final String message;
  final int? statusCode;
  final Map<String, List<String>> errors;

  bool get isUnauthorized => statusCode == 401;
  bool get isOffline => statusCode == null;

  /// The first error for a form field, if any.
  String? field(String name) => errors[name]?.firstOrNull;

  /// Every field error on its own line, or the general message.
  String get details => errors.isEmpty ? message : errors.values.expand((e) => e).join('\n');

  @override
  String toString() => message;
}

/// A page of results from a paginated endpoint.
class Paged<T> {
  Paged(this.items, {required this.currentPage, required this.lastPage, required this.total});

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}

/// Thin wrapper around Dio that speaks the API's envelope:
/// `{success, message, data, meta?, errors?}`.
class Api {
  Api({String? baseUrl, HttpClientAdapter? adapter})
      : _dio = Dio(BaseOptions(
          baseUrl: '${baseUrl ?? apiBaseUrl}/api/v1/',
          connectTimeout: const Duration(seconds: 20),
          receiveTimeout: const Duration(seconds: 30),
          headers: {'Accept': 'application/json'},
        )) {
    if (adapter != null) _dio.httpClientAdapter = adapter;
  }

  final Dio _dio;
  String _locale = 'sw';

  /// The member's API token, when signed in.
  String? token;

  /// Called when the server says the session is no longer valid.
  void Function()? onUnauthorized;

  String get baseUrl => _dio.options.baseUrl.replaceFirst(RegExp(r'/api/v1/$'), '');
  set locale(String value) => _locale = value;

  Map<String, String> get authHeaders => {
        if (token != null) 'Authorization': 'Bearer $token',
        'Accept-Language': _locale,
      };

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async =>
      (await _send(() => _dio.get(path, queryParameters: _clean(query), options: _options()))).data;

  Future<Map<String, dynamic>> getEnvelope(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get(path, queryParameters: _clean(query), options: _options())).then((r) => r.envelope);

  Future<Map<String, dynamic>> post(String path, [Object? body]) =>
      _send(() => _dio.post(path, data: body, options: _options())).then((r) => r.envelope);

  Future<Map<String, dynamic>> put(String path, [Object? body]) =>
      _send(() => _dio.put(path, data: body, options: _options())).then((r) => r.envelope);

  Future<Map<String, dynamic>> delete(String path, [Object? body]) =>
      _send(() => _dio.delete(path, data: body, options: _options())).then((r) => r.envelope);

  /// A paginated list; [map] turns each row into a value.
  Future<Paged<T>> paged<T>(String path, T Function(Map<String, dynamic>) map, {Map<String, dynamic>? query}) async {
    final envelope = await getEnvelope(path, query: query);
    final meta = (envelope['meta'] as Map?)?.cast<String, dynamic>() ?? const {};
    final items = (envelope['data'] as List? ?? const []).map((row) => map((row as Map).cast<String, dynamic>())).toList();

    return Paged(
      items,
      currentPage: (meta['current_page'] as num?)?.toInt() ?? 1,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
      total: (meta['total'] as num?)?.toInt() ?? items.length,
    );
  }

  Options _options() => Options(headers: authHeaders);

  Map<String, dynamic>? _clean(Map<String, dynamic>? query) =>
      query == null ? null : (Map.of(query)..removeWhere((_, value) => value == null || value == ''));

  Future<_Result> _send(Future<Response<dynamic>> Function() request) async {
    try {
      final response = await request();
      return _Result(response.data);
    } on DioException catch (error) {
      throw _toException(error);
    }
  }

  ApiException _toException(DioException error) {
    final response = error.response;

    if (response == null) {
      return ApiException(switch (error.type) {
        DioExceptionType.connectionTimeout || DioExceptionType.receiveTimeout || DioExceptionType.sendTimeout => 'timeout',
        _ => 'offline',
      });
    }

    final body = response.data is Map ? (response.data as Map).cast<String, dynamic>() : const <String, dynamic>{};
    final errors = <String, List<String>>{};

    if (body['errors'] is Map) {
      (body['errors'] as Map).forEach((key, value) {
        errors['$key'] = value is List ? value.map((e) => '$e').toList() : ['$value'];
      });
    }

    if (response.statusCode == 401 && token != null) {
      onUnauthorized?.call();
    }

    return ApiException(
      (body['message'] as String?)?.trim().isNotEmpty == true ? body['message'] as String : 'server_error',
      statusCode: response.statusCode,
      errors: errors,
    );
  }
}

class _Result {
  _Result(this.raw);

  final dynamic raw;

  Map<String, dynamic> get envelope => raw is Map ? (raw as Map).cast<String, dynamic>() : {'data': raw};

  dynamic get data => envelope['data'];
}

/// Convenience readers for the loosely typed JSON the API returns.
extension JsonReader on Map<String, dynamic> {
  String str(String key, [String fallback = '']) {
    final value = this[key];
    return value == null ? fallback : '$value';
  }

  String? strOrNull(String key) {
    final value = this[key];
    return value == null || '$value'.isEmpty ? null : '$value';
  }

  int integer(String key, [int fallback = 0]) {
    final value = this[key];
    if (value is num) return value.toInt();
    return int.tryParse('${value ?? ''}') ?? fallback;
  }

  int? intOrNull(String key) {
    final value = this[key];
    if (value is num) return value.toInt();
    return int.tryParse('${value ?? ''}');
  }

  double number(String key, [double fallback = 0]) {
    final value = this[key];
    if (value is num) return value.toDouble();
    return double.tryParse('${value ?? ''}') ?? fallback;
  }

  bool flag(String key) {
    final value = this[key];
    return value == true || value == 1 || value == '1' || value == 'true';
  }

  DateTime? date(String key) {
    final value = this[key];
    return value == null ? null : DateTime.tryParse('$value')?.toLocal();
  }

  Map<String, dynamic>? obj(String key) {
    final value = this[key];
    return value is Map ? value.cast<String, dynamic>() : null;
  }

  List<Map<String, dynamic>> list(String key) {
    final value = this[key];
    return value is List ? value.whereType<Map>().map((e) => e.cast<String, dynamic>()).toList() : const [];
  }

  List<String> strings(String key) {
    final value = this[key];
    return value is List ? value.map((e) => '$e').toList() : const [];
  }
}

/// Reads `data` from a single-object response.
Map<String, dynamic> dataOf(Map<String, dynamic> envelope) =>
    envelope['data'] is Map ? (envelope['data'] as Map).cast<String, dynamic>() : const {};

/// Reads `data` from a list response.
List<Map<String, dynamic>> listOf(dynamic data) =>
    data is List ? data.whereType<Map>().map((e) => e.cast<String, dynamic>()).toList() : const [];
