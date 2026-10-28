import 'package:flutter/material.dart';

/// TRA colours, as on the website.
class Brand {
  static const yellow = Color(0xFFF9E50F);
  static const yellowWash = Color(0xFFFFF9C2);
  static const ink = Color(0xFF020202);
  static const charcoal = Color(0xFF222222);
  static const slate = Color(0xFF4B5563);
  static const line = Color(0xFFE5E7EB);
  static const mist = Color(0xFFF6F6F3);
  static const success = Color(0xFF15803D);
  static const danger = Color(0xFFB42318);
  static const info = Color(0xFF1D4ED8);
}

ThemeData buildTheme() {
  const scheme = ColorScheme(
    brightness: Brightness.light,
    primary: Brand.ink,
    onPrimary: Colors.white,
    primaryContainer: Brand.yellow,
    onPrimaryContainer: Brand.ink,
    secondary: Brand.yellow,
    onSecondary: Brand.ink,
    secondaryContainer: Brand.yellowWash,
    onSecondaryContainer: Brand.ink,
    tertiary: Brand.success,
    onTertiary: Colors.white,
    error: Brand.danger,
    onError: Colors.white,
    surface: Colors.white,
    onSurface: Brand.ink,
    onSurfaceVariant: Brand.slate,
    surfaceContainerLowest: Colors.white,
    surfaceContainerLow: Brand.mist,
    surfaceContainer: Brand.mist,
    surfaceContainerHigh: Color(0xFFEFEFEA),
    surfaceContainerHighest: Color(0xFFE8E8E2),
    outline: Color(0xFF9CA3AF),
    outlineVariant: Brand.line,
  );

  final base = ThemeData(colorScheme: scheme, useMaterial3: true, scaffoldBackgroundColor: Brand.mist);

  return base.copyWith(
    appBarTheme: const AppBarTheme(
      backgroundColor: Brand.yellow,
      foregroundColor: Brand.ink,
      elevation: 0,
      scrolledUnderElevation: 1,
      centerTitle: false,
      titleTextStyle: TextStyle(color: Brand.ink, fontSize: 19, fontWeight: FontWeight.w700),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: const BorderSide(color: Brand.line)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(64, 50),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(64, 50),
        foregroundColor: Brand.ink,
        side: const BorderSide(color: Brand.ink),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(foregroundColor: Brand.ink)),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFD1D5DB))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Brand.ink, width: 2)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: Brand.yellow,
      labelTextStyle: WidgetStateProperty.all(const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
    ),
    chipTheme: base.chipTheme.copyWith(
      selectedColor: Brand.yellow,
      side: const BorderSide(color: Brand.line),
      labelStyle: const TextStyle(color: Brand.ink),
    ),
    snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    dividerTheme: const DividerThemeData(color: Brand.line, space: 1),
    listTileTheme: const ListTileThemeData(iconColor: Brand.charcoal),
  );
}
