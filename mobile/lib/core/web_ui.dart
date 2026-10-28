import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import 'i18n.dart';
import 'theme.dart';

/// Building blocks that copy the website's look (resources/css/app.css),
/// so the app and the website feel like one product.

/// The website's "sun-hatch": TRA yellow with faint diagonal stripes.
class Hatch extends StatelessWidget {
  const Hatch({
    super.key,
    required this.child,
    this.borderBottom = true,
    this.padding = const EdgeInsets.fromLTRB(16, 40, 16, 40),
  });

  final Widget child;
  final bool borderBottom;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Brand.yellow,
        border: borderBottom
            ? const Border(bottom: BorderSide(color: Brand.ink, width: 4))
            : null,
      ),
      child: CustomPaint(
        painter: const HatchPainter(),
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

class HatchPainter extends CustomPainter {
  const HatchPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0x09000000)
      ..strokeWidth = 2;
    for (double x = -size.height; x < size.width; x += 14) {
      canvas.drawLine(
        Offset(x, 0),
        Offset(x + size.height, size.height),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Big black page heading with the slate line under it (member pages).
class PageTitle extends StatelessWidget {
  const PageTitle(
    this.title, {
    super.key,
    this.subtitle,
    this.back,
    this.onBack,
  });

  final String title;
  final String? subtitle;
  final String? back;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (back != null) ...[
          LinkText('← $back', onTap: onBack),
          const SizedBox(height: 14),
        ],
        Text(
          title,
          style: const TextStyle(
            fontSize: 30,
            fontWeight: FontWeight.w900,
            height: 1.1,
            letterSpacing: -0.5,
          ),
        ),
        if (subtitle != null && subtitle!.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            subtitle!,
            style: const TextStyle(
              fontSize: 18,
              color: Brand.slate,
              height: 1.45,
            ),
          ),
        ],
      ],
    );
  }
}

/// Section heading with an optional underlined link on the right.
class SectionHeading extends StatelessWidget {
  const SectionHeading(
    this.title, {
    super.key,
    this.link,
    this.onLink,
    this.size = 24,
  });

  final String title;
  final String? link;
  final VoidCallback? onLink;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              fontSize: size,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.3,
              height: 1.15,
            ),
          ),
        ),
        if (link != null)
          Padding(
            padding: const EdgeInsets.only(left: 12, bottom: 2),
            child: LinkText(link!, onTap: onLink),
          ),
      ],
    );
  }
}

/// The website's ".link": bold text with a thick yellow underline.
class LinkText extends StatelessWidget {
  const LinkText(this.text, {super.key, this.onTap, this.color = Brand.ink});

  final String text;
  final VoidCallback? onTap;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(4),
      child: Container(
        padding: const EdgeInsets.only(bottom: 1),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: Brand.yellow, width: 3)),
        ),
        child: Text(
          text,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: color,
            fontSize: 16,
          ),
        ),
      ),
    );
  }
}

enum BtnKind { primary, ghost, brand, onDark }

/// The website's buttons: .btn-primary (black), .btn-ghost (outlined),
/// .btn-brand (yellow) and the outlined white button on dark panels.
class WebButton extends StatefulWidget {
  const WebButton(
    this.label, {
    super.key,
    this.onPressed,
    this.kind = BtnKind.primary,
    this.icon,
    this.expand = true,
    this.large = false,
  });

  final String label;
  final Future<void> Function()? onPressed;
  final BtnKind kind;
  final IconData? icon;
  final bool expand;
  final bool large;

  @override
  State<WebButton> createState() => _WebButtonState();
}

class _WebButtonState extends State<WebButton> {
  bool _busy = false;

  Future<void> _run() async {
    setState(() => _busy = true);
    try {
      await widget.onPressed!();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final (background, foreground, border) = switch (widget.kind) {
      BtnKind.primary => (Brand.ink, Colors.white, Brand.ink),
      BtnKind.ghost => (Colors.white, Brand.ink, Brand.ink),
      BtnKind.brand => (Brand.yellow, Brand.ink, Brand.yellow),
      BtnKind.onDark => (
        Colors.transparent,
        Colors.white,
        const Color(0x66FFFFFF),
      ),
    };
    final enabled = widget.onPressed != null && !_busy;

    final button = Opacity(
      opacity: widget.onPressed == null ? 0.6 : 1,
      child: Material(
        color: background,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: border,
            width:
                widget.kind == BtnKind.primary || widget.kind == BtnKind.brand
                ? 0
                : 2,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: enabled ? _run : null,
          child: Container(
            constraints: BoxConstraints(minHeight: widget.large ? 54 : 48),
            padding: EdgeInsets.symmetric(
              horizontal: widget.large ? 28 : 22,
              vertical: 12,
            ),
            decoration: widget.kind == BtnKind.brand
                ? const BoxDecoration(
                    border: Border(
                      bottom: BorderSide(color: Color(0xFFD9C600), width: 3),
                    ),
                  )
                : null,
            child: Row(
              mainAxisSize: widget.expand ? MainAxisSize.max : MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: _busy
                  ? [
                      SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: foreground,
                        ),
                      ),
                    ]
                  : [
                      if (widget.icon != null) ...[
                        Icon(widget.icon, size: 20, color: foreground),
                        const SizedBox(width: 8),
                      ],
                      Flexible(
                        child: Text(
                          widget.label,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: foreground,
                            fontWeight: FontWeight.w800,
                            fontSize: widget.large ? 18 : 16,
                          ),
                        ),
                      ),
                    ],
            ),
          ),
        ),
      ),
    );

    return widget.expand
        ? SizedBox(width: double.infinity, child: button)
        : button;
  }
}

/// Yellow rounded tag, e.g. the lesson type.
class Tag extends StatelessWidget {
  const Tag(this.text, {super.key, this.dark = false, this.color});

  final String text;
  final bool dark;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: color ?? (dark ? Brand.ink : Brand.yellow),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontWeight: FontWeight.w800,
          fontSize: 13,
          color: dark ? Brand.yellow : Brand.ink,
        ),
      ),
    );
  }
}

/// White card with the website's 2px light border.
class WebCard extends StatelessWidget {
  const WebCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(20),
    this.color = Colors.white,
    this.highlight = false,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsets padding;
  final Color color;

  /// The black border used for the current or selected item.
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: highlight ? Brand.ink : Brand.line, width: 2),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

/// A grey box for empty states, as on the website.
class MistBox extends StatelessWidget {
  const MistBox(
    this.text, {
    super.key,
    this.color = Brand.mist,
    this.center = false,
  });

  final String text;
  final Color color;
  final bool center;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Text(
        text,
        textAlign: center ? TextAlign.center : TextAlign.start,
        style: const TextStyle(
          fontSize: 17,
          color: Brand.slate,
          height: 1.5,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

/// Points-style statistic box.
class StatBox extends StatelessWidget {
  const StatBox({
    super.key,
    required this.label,
    required this.value,
    this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return WebCard(
      onTap: onTap,
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: Brand.slate,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 36,
              fontWeight: FontWeight.w900,
              height: 1.1,
            ),
          ),
        ],
      ),
    );
  }
}

/// The dark call-to-action panel with a yellow heading.
class DarkPanel extends StatelessWidget {
  const DarkPanel({
    super.key,
    required this.title,
    required this.text,
    this.actions = const [],
    this.topBorder = false,
  });

  final String title;
  final String text;
  final List<Widget> actions;
  final bool topBorder;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: Brand.ink,
        borderRadius: BorderRadius.circular(24),
        border: topBorder
            ? const Border(top: BorderSide(color: Brand.yellow, width: 8))
            : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w900,
              color: topBorder ? Colors.white : Brand.yellow,
              height: 1.15,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            text,
            style: const TextStyle(
              fontSize: 17,
              color: Color(0xCCFFFFFF),
              height: 1.5,
            ),
          ),
          if (actions.isNotEmpty) ...[
            const SizedBox(height: 22),
            Wrap(spacing: 12, runSpacing: 12, children: actions),
          ],
        ],
      ),
    );
  }
}

/// Lesson card from learn/partials/card.blade.php.
class LessonCardView extends StatelessWidget {
  const LessonCardView({
    super.key,
    required this.type,
    required this.title,
    this.summary,
    this.date,
    this.onTap,
    this.action,
  });

  final String type;
  final String title;
  final String? summary;
  final DateTime? date;
  final VoidCallback? onTap;
  final String? action;

  @override
  Widget build(BuildContext context) {
    return WebCard(
      onTap: onTap,
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Tag(type),
              if (date != null) ...[
                const SizedBox(width: 12),
                Text(
                  context.date(date),
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Brand.slate,
                    fontSize: 14,
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 16),
          Text(
            title,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              height: 1.3,
            ),
          ),
          if (summary != null && summary!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              summary!,
              maxLines: 5,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Brand.slate,
                fontSize: 16,
                height: 1.55,
              ),
            ),
          ],
          const SizedBox(height: 18),
          LinkText(action ?? context.tr('Read lesson'), onTap: onTap),
        ],
      ),
    );
  }
}

/// Event row from events/partials/row.blade.php: black date tile.
class EventRowView extends StatelessWidget {
  const EventRowView({
    super.key,
    required this.title,
    this.startsAt,
    this.location,
    this.onTap,
    this.trailing,
  });

  final String title;
  final DateTime? startsAt;
  final String? location;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return WebCard(
      onTap: onTap,
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 72,
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              color: startsAt == null ? Brand.mist : Brand.ink,
              borderRadius: BorderRadius.circular(12),
            ),
            child: startsAt == null
                ? const SizedBox(height: 40)
                : Column(
                    children: [
                      Text(
                        context.date(startsAt, pattern: 'MMM').toUpperCase(),
                        style: const TextStyle(
                          color: Brand.yellow,
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        context.date(startsAt, pattern: 'd'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 30,
                          height: 1,
                        ),
                      ),
                    ],
                  ),
          ),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 4),
                if (startsAt != null)
                  Text(
                    context.date(startsAt, pattern: 'EEEE, d MMMM y · HH:mm'),
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: Brand.slate,
                    ),
                  ),
                if (location != null && location!.isNotEmpty)
                  Text(
                    location!,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: Brand.slate,
                    ),
                  ),
                if (trailing != null) ...[const SizedBox(height: 8), trailing!],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A white field box with a light label (events/show "When", "Where").
class FactBox extends StatelessWidget {
  const FactBox({super.key, required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: Brand.line, width: 2),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: Brand.slate,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
        ],
      ),
    );
  }
}

/// Heading with the thick yellow bar on its left (about page).
class BarHeading extends StatelessWidget {
  const BarHeading(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.only(left: 16),
      decoration: const BoxDecoration(
        border: Border(left: BorderSide(color: Brand.yellow, width: 8)),
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
      ),
    );
  }
}

class Bullets extends StatelessWidget {
  const Bullets(this.items, {super.key});

  final List<String> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Padding(
                  padding: EdgeInsets.only(top: 9, right: 12, left: 6),
                  child: CircleAvatar(radius: 3.5, backgroundColor: Brand.ink),
                ),
                Expanded(
                  child: Text(
                    item,
                    style: const TextStyle(
                      fontSize: 18,
                      height: 1.55,
                      color: Brand.charcoal,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// Initials such as "AJ" for Asha Juma.
String initialsOf(String name) => name
    .trim()
    .split(RegExp(r'\s+'))
    .where((part) => part.isNotEmpty)
    .take(2)
    .map((part) => part[0].toUpperCase())
    .join();

/// The membership card (components/membership-card.blade.php).
class MembershipCardView extends StatelessWidget {
  const MembershipCardView({
    super.key,
    required this.name,
    required this.number,
    this.institution,
    this.category,
    this.memberSince,
    required this.statusLabel,
    required this.valid,
    this.qrData,
    this.photo,
  });

  final String name;
  final String number;
  final String? institution;
  final String? category;
  final DateTime? memberSince;
  final String statusLabel;
  final bool valid;
  final String? qrData;
  final ImageProvider? photo;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(right: 8, bottom: 8),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: Brand.ink, width: 4),
        boxShadow: const [BoxShadow(color: Brand.ink, offset: Offset(8, 8))],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: Hatch(
          borderBottom: false,
          padding: const EdgeInsets.all(22),
          child: Stack(
            children: [
              Positioned(
                right: -26,
                bottom: -26,
                child: Opacity(
                  opacity: 0.1,
                  child: Image.asset('assets/images/tra-logo.png', height: 150),
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Image.asset('assets/images/tra-logo.png', height: 42),
                      const SizedBox(width: 12),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'TRA Tax Club',
                            style: TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          Text(
                            context.tr('Membership card'),
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                              color: Brand.charcoal,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 22),
                  Row(
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          border: Border.all(color: Brand.ink, width: 2),
                          borderRadius: BorderRadius.circular(16),
                          image: photo == null
                              ? null
                              : DecorationImage(
                                  image: photo!,
                                  fit: BoxFit.cover,
                                ),
                        ),
                        alignment: Alignment.center,
                        child: photo == null
                            ? Text(
                                initialsOf(name),
                                style: const TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w900,
                                ),
                              )
                            : null,
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              name,
                              style: const TextStyle(
                                fontSize: 23,
                                fontWeight: FontWeight.w900,
                                height: 1.15,
                              ),
                            ),
                            if (institution != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                institution!,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: Brand.charcoal,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 22),
                  Text(
                    context.tr('Membership no.'),
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Brand.charcoal,
                    ),
                  ),
                  Text(
                    number.isEmpty ? '—' : number,
                    style: const TextStyle(
                      fontFamily: 'IBMPlexMono',
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.tr('Category'),
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: Brand.charcoal,
                              ),
                            ),
                            Text(
                              category ?? '—',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (memberSince != null)
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                context.tr('Member since'),
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: Brand.charcoal,
                                ),
                              ),
                              Text(
                                context.date(memberSince, pattern: 'MMM y'),
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Align(
                          alignment: Alignment.bottomLeft,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 7,
                            ),
                            decoration: BoxDecoration(
                              color: valid ? Brand.ink : Brand.danger,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  width: 10,
                                  height: 10,
                                  decoration: BoxDecoration(
                                    color: valid
                                        ? const Color(0xFF4ADE80)
                                        : Colors.white,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Flexible(
                                  child: Text(
                                    statusLabel,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 14,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      if (qrData != null && valid)
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            border: Border.all(color: Brand.ink, width: 2),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: QrImageView(
                            data: qrData!,
                            size: 116,
                            padding: EdgeInsets.zero,
                            backgroundColor: Colors.white,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The EFD machine printing a membership receipt (home page hero).
class ReceiptHero extends StatelessWidget {
  const ReceiptHero({super.key});

  @override
  Widget build(BuildContext context) {
    final items = [
      'Tax lessons',
      'Quizzes and badges',
      'National events',
      'Digital card',
      'Certificates',
    ];
    const mono = TextStyle(
      fontFamily: 'IBMPlexMono',
      fontSize: 13,
      height: 1.7,
      color: Brand.ink,
      fontWeight: FontWeight.w500,
    );

    return Container(
      padding: const EdgeInsets.fromLTRB(22, 18, 22, 44),
      decoration: BoxDecoration(
        color: Brand.ink,
        borderRadius: BorderRadius.circular(28),
        boxShadow: const [
          BoxShadow(
            color: Color(0x8C000000),
            blurRadius: 60,
            offset: Offset(0, 30),
            spreadRadius: -20,
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xFF1D2A12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'TRA·EFD  TSh 0.00',
                  style: TextStyle(
                    fontFamily: 'IBMPlexMono',
                    color: Color(0xFFC6F36B),
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    letterSpacing: 1,
                  ),
                ),
              ),
              const Spacer(),
              const CircleAvatar(radius: 5, backgroundColor: Brand.yellow),
              const SizedBox(width: 6),
              const CircleAvatar(radius: 5, backgroundColor: Color(0x40FFFFFF)),
            ],
          ),
          const SizedBox(height: 18),
          Container(
            height: 12,
            decoration: BoxDecoration(
              color: Colors.black,
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          ClipPath(
            clipper: const _ZigZagClipper(),
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: 6),
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(24, 24, 24, 30),
              child: Column(
                children: [
                  Text(
                    'TRA TAX CLUB',
                    style: mono.copyWith(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  Text(context.tr('Membership receipt'), style: mono),
                  Text(
                    '${context.tr('Receipt no.')} TC-${DateTime.now().year % 100}-000001',
                    style: mono.copyWith(color: Brand.slate),
                  ),
                  const _Dashed(),
                  for (final item in items)
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(context.tr(item), style: mono, softWrap: false),
                        const Expanded(child: _Dots()),
                        Text('0.00', style: mono),
                      ],
                    ),
                  const _Dashed(),
                  Row(
                    children: [
                      Text(
                        context.tr('Total'),
                        style: mono.copyWith(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        'TSh 0.00',
                        style: mono.copyWith(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      context.tr('Membership is free for students.'),
                      style: mono.copyWith(color: Brand.slate),
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    height: 40,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (final bar in const [
                          3,
                          1,
                          2,
                          1,
                          1,
                          3,
                          2,
                          1,
                          3,
                          1,
                          2,
                          2,
                          1,
                          3,
                          1,
                          1,
                          2,
                          3,
                          1,
                          2,
                          1,
                          3,
                          2,
                          1,
                          1,
                          2,
                          3,
                          1,
                        ])
                          Container(
                            width: bar.toDouble(),
                            margin: const EdgeInsets.only(right: 2),
                            color: Brand.ink,
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    context.tr('Ask for a receipt every time you buy.'),
                    textAlign: TextAlign.center,
                    style: mono.copyWith(fontSize: 12, color: Brand.slate),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Dashed extends StatelessWidget {
  const _Dashed();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: LayoutBuilder(
        builder: (context, constraints) => Row(
          children: List.generate(
            (constraints.maxWidth / 6).floor(),
            (_) => Container(
              width: 3,
              height: 1,
              margin: const EdgeInsets.only(right: 3),
              color: const Color(0x66020202),
            ),
          ),
        ),
      ),
    );
  }
}

class _Dots extends StatelessWidget {
  const _Dots();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 6, right: 6, bottom: 7),
      child: LayoutBuilder(
        builder: (context, constraints) => Row(
          children: List.generate(
            (constraints.maxWidth / 6).floor(),
            (_) => Container(
              width: 1.5,
              height: 1.5,
              margin: const EdgeInsets.only(right: 4.5),
              color: Brand.ink,
            ),
          ),
        ),
      ),
    );
  }
}

/// The torn, zig-zag bottom edge of the receipt.
class _ZigZagClipper extends CustomClipper<Path> {
  const _ZigZagClipper();

  @override
  Path getClip(Size size) {
    const tooth = 10.0;
    final path = Path()..lineTo(0, size.height - tooth);
    var x = 0.0;
    while (x < size.width) {
      path.lineTo(x + tooth / 2, size.height);
      path.lineTo(x + tooth, size.height - tooth);
      x += tooth;
    }
    return path
      ..lineTo(size.width, 0)
      ..close();
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

/// Black circle with a yellow tick (home page "Free for students" list).
class TickLine extends StatelessWidget {
  const TickLine(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Container(
            width: 20,
            height: 20,
            decoration: const BoxDecoration(
              color: Brand.ink,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.check, size: 14, color: Brand.yellow),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              text,
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                color: Brand.charcoal,
                fontSize: 16,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Search box with the black offset shadow (learn and answers pages).
class ShadowSearch extends StatelessWidget {
  const ShadowSearch({
    super.key,
    required this.controller,
    required this.hint,
    required this.onSearch,
  });

  final TextEditingController controller;
  final String hint;
  final VoidCallback onSearch;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [BoxShadow(color: Brand.ink, offset: Offset(6, 6))],
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => onSearch(),
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
              decoration: InputDecoration(
                hintText: hint,
                filled: false,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                hintStyle: const TextStyle(
                  fontWeight: FontWeight.w400,
                  color: Color(0x994B5563),
                ),
              ),
            ),
          ),
          WebButton(
            context.tr('Search'),
            expand: false,
            onPressed: () async => onSearch(),
          ),
        ],
      ),
    );
  }
}
