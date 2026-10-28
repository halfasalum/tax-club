import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'api.dart';
import 'i18n.dart';
import 'session.dart';
import 'theme.dart';
import 'web_ui.dart';
import 'widgets.dart';

/// TRA logo with "Tax Club / Tanzania Revenue Authority" (partials/brand).
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.inverse = false, this.compact = false});

  final bool inverse;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final color = inverse ? Colors.white : Brand.ink;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          padding: inverse ? const EdgeInsets.all(4) : EdgeInsets.zero,
          decoration: inverse
              ? BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                )
              : null,
          child: Image.asset(
            'assets/images/tra-logo.png',
            height: compact ? 30 : 40,
          ),
        ),
        const SizedBox(width: 12),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Tax Club',
              style: TextStyle(
                fontWeight: FontWeight.w900,
                fontSize: compact ? 16 : 19,
                color: color,
                height: 1.1,
              ),
            ),
            if (!compact)
              Text(
                context.tr('Tanzania Revenue Authority'),
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 11.5,
                  color: inverse ? const Color(0xBFFFFFFF) : Brand.charcoal,
                ),
              ),
          ],
        ),
      ],
    );
  }
}

/// Square outlined button used for the menu and back actions in headers.
class SquareButton extends StatelessWidget {
  const SquareButton({
    super.key,
    required this.icon,
    required this.onTap,
    required this.tooltip,
    this.size = 46,
    this.border = 2,
  });

  final IconData icon;
  final VoidCallback onTap;
  final String tooltip;
  final double size;
  final double border;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: border > 1 ? Brand.ink : Brand.line,
            width: border,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: SizedBox(
            width: size,
            height: size,
            child: Icon(icon, size: 24),
          ),
        ),
      ),
    );
  }
}

// ─── Public website frame ──────────────────────────────────────────

/// Utility bar, header with menu, page content and the black footer —
/// the public website's layout (components/layouts/public.blade.php).
class PublicScaffold extends StatelessWidget {
  const PublicScaffold({
    super.key,
    required this.children,
    this.footer = true,
    this.current,
  });

  final List<Widget> children;
  final bool footer;

  /// The menu item to highlight: learn, events, answers or about.
  final String? current;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final other = session.locale == 'sw' ? 'en' : 'sw';

    return Scaffold(
      backgroundColor: Colors.white,
      body: Column(
        children: [
          Container(
            color: Brand.ink,
            padding: EdgeInsets.fromLTRB(
              16,
              MediaQuery.of(context).padding.top + 8,
              16,
              8,
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    context.tr(
                      'An official programme of the Tanzania Revenue Authority',
                    ),
                    style: const TextStyle(
                      color: Color(0xCCFFFFFF),
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                InkWell(
                  onTap: () => session.setLocale(other),
                  child: Row(
                    children: [
                      const Icon(Icons.language, size: 16, color: Brand.yellow),
                      const SizedBox(width: 6),
                      Text(
                        other == 'en' ? 'English' : 'Kiswahili',
                        style: const TextStyle(
                          color: Brand.yellow,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(bottom: BorderSide(color: Brand.yellow, width: 4)),
            ),
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            child: Row(
              children: [
                InkWell(onTap: () => context.go('/'), child: const BrandMark()),
                const Spacer(),
                SquareButton(
                  icon: Icons.menu,
                  tooltip: context.tr('Menu'),
                  onTap: () => _openMenu(context),
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [...children, if (footer) const SiteFooter()],
            ),
          ),
        ],
      ),
    );
  }

  void _openMenu(BuildContext context) {
    final items = {
      'learn': ('/site/learn', 'Learn'),
      'events': ('/site/events', 'Events'),
      'answers': ('/site/answers', 'Questions'),
      'about': ('/site/about', 'About Tax Club'),
    };

    showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: context.tr('Close menu'),
      barrierColor: const Color(0x66000000),
      transitionDuration: const Duration(milliseconds: 180),
      pageBuilder: (dialogContext, _, _) => Align(
        alignment: Alignment.topCenter,
        child: Material(
          color: Colors.white,
          child: Container(
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: Brand.yellow, width: 4)),
            ),
            padding: EdgeInsets.fromLTRB(
              16,
              MediaQuery.of(context).padding.top + 10,
              16,
              24,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    const BrandMark(),
                    const Spacer(),
                    SquareButton(
                      icon: Icons.close,
                      tooltip: dialogContext.tr('Close menu'),
                      onTap: () => Navigator.pop(dialogContext),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                for (final entry in items.entries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Material(
                      color: entry.key == current
                          ? Brand.yellow
                          : Colors.transparent,
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () {
                          Navigator.pop(dialogContext);
                          context.go(entry.value.$1);
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 15,
                          ),
                          child: Text(
                            dialogContext.tr(entry.value.$2),
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                WebButton(
                  dialogContext.tr('Join Tax Club'),
                  kind: BtnKind.brand,
                  onPressed: () async {
                    Navigator.pop(dialogContext);
                    context.push('/register');
                  },
                ),
                const SizedBox(height: 8),
                WebButton(
                  dialogContext.tr('Sign in'),
                  kind: BtnKind.ghost,
                  onPressed: () async {
                    Navigator.pop(dialogContext);
                    context.push('/login');
                  },
                ),
              ],
            ),
          ),
        ),
      ),
      transitionBuilder: (_, animation, _, child) => SlideTransition(
        position: Tween(
          begin: const Offset(0, -0.2),
          end: Offset.zero,
        ).animate(CurvedAnimation(parent: animation, curve: Curves.easeOut)),
        child: FadeTransition(opacity: animation, child: child),
      ),
    );
  }
}

class SiteFooter extends StatelessWidget {
  const SiteFooter({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    Widget link(String label, VoidCallback onTap) => Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: InkWell(
        onTap: onTap,
        child: Text(
          label,
          style: const TextStyle(color: Color(0xD9FFFFFF), fontSize: 16),
        ),
      ),
    );

    return Container(
      margin: const EdgeInsets.only(top: 64),
      color: Brand.ink,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(height: 8, color: Brand.yellow),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 40, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const BrandMark(inverse: true),
                const SizedBox(height: 18),
                Text(
                  context.tr(
                    'A Tanzania Revenue Authority programme for students in secondary schools, colleges and universities.',
                  ),
                  style: const TextStyle(
                    color: Color(0xBFFFFFFF),
                    fontSize: 16,
                    height: 1.6,
                  ),
                ),
                const SizedBox(height: 36),
                Text(
                  context.tr('Explore'),
                  style: const TextStyle(
                    color: Brand.yellow,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 16),
                link(context.tr('Learn'), () => context.go('/site/learn')),
                link(context.tr('Events'), () => context.go('/site/events')),
                link(
                  context.tr('About Tax Club'),
                  () => context.go('/site/about'),
                ),
                link(
                  'tra.go.tz',
                  () => openLink(context, 'https://www.tra.go.tz'),
                ),
                const SizedBox(height: 22),
                Text(
                  context.tr('Members'),
                  style: const TextStyle(
                    color: Brand.yellow,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 16),
                link(
                  context.tr('Join Tax Club'),
                  () => context.push('/register'),
                ),
                link(context.tr('Sign in'), () => context.push('/login')),
                link(
                  session.locale == 'sw' ? 'English' : 'Kiswahili',
                  () => session.setLocale(session.locale == 'sw' ? 'en' : 'sw'),
                ),
              ],
            ),
          ),
          Container(
            width: double.infinity,
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: Color(0x1AFFFFFF))),
            ),
            padding: EdgeInsets.fromLTRB(
              16,
              18,
              16,
              18 + MediaQuery.of(context).padding.bottom,
            ),
            child: Text(
              '© ${DateTime.now().year} Tanzania Revenue Authority. ${context.tr('Remember: ask for a receipt every time you buy.')}',
              style: const TextStyle(color: Color(0x99FFFFFF), fontSize: 14),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Member area frame ─────────────────────────────────────────────

class _NavItem {
  const _NavItem(this.route, this.label, this.icon, {this.minorHidden = false});

  final String route;
  final String label;
  final IconData icon;
  final bool minorHidden;
}

const _memberMenu = <String?, List<_NavItem>>{
  null: [
    _NavItem('/home', 'My Tax Club', Icons.home_outlined),
    _NavItem('/learn', 'Learn', Icons.menu_book_outlined),
    _NavItem('/quizzes', 'Quizzes', Icons.help_outline),
    _NavItem('/competitions', 'Competitions', Icons.military_tech_outlined),
    _NavItem('/events', 'Events', Icons.calendar_month_outlined),
    _NavItem('/opportunities', 'Opportunities', Icons.work_outline),
    _NavItem('/leaderboard', 'Points', Icons.emoji_events_outlined),
  ],
  'Connect': [
    _NavItem('/community', 'Community', Icons.chat_outlined),
    _NavItem('/ask', 'Ask TRA', Icons.contact_support_outlined),
    _NavItem('/alumni', 'Alumni', Icons.school_outlined, minorHidden: true),
    _NavItem('/mentorship', 'Mentorship', Icons.handshake_outlined),
  ],
  'My account': [
    _NavItem('/card', 'My card', Icons.badge_outlined),
    _NavItem(
      '/achievements',
      'My achievements',
      Icons.workspace_premium_outlined,
    ),
    _NavItem('/notifications', 'Notifications', Icons.inbox_outlined),
    _NavItem('/profile', 'My profile', Icons.person_outline),
    _NavItem('/feedback', 'Feedback', Icons.feedback_outlined),
  ],
};

/// Header with menu, bell and avatar, the black side menu and a grey page
/// background — the member area of the website (layouts/app.blade.php).
class MemberScaffold extends StatelessWidget {
  const MemberScaffold({
    super.key,
    required this.body,
    this.floatingActionButton,
    this.white = false,
  });

  final Widget body;
  final Widget? floatingActionButton;
  final bool white;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final canPop = GoRouter.of(context).canPop();
    final photo = session.user.strOrNull('photo_url');

    return Scaffold(
      backgroundColor: white ? Colors.white : Brand.mist,
      drawer: const MemberDrawer(),
      floatingActionButton: floatingActionButton,
      body: Column(
        children: [
          Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(bottom: BorderSide(color: Brand.yellow, width: 4)),
            ),
            padding: EdgeInsets.fromLTRB(
              16,
              MediaQuery.of(context).padding.top + 8,
              16,
              8,
            ),
            child: Row(
              children: [
                Builder(
                  builder: (context) => canPop
                      ? SquareButton(
                          icon: Icons.arrow_back,
                          tooltip: context.tr('Back'),
                          border: 1.5,
                          size: 42,
                          onTap: () => context.pop(),
                        )
                      : SquareButton(
                          icon: Icons.menu,
                          tooltip: context.tr('Main menu'),
                          border: 1.5,
                          size: 42,
                          onTap: () => Scaffold.of(context).openDrawer(),
                        ),
                ),
                const SizedBox(width: 12),
                InkWell(
                  onTap: () => context.go('/home'),
                  child: const BrandMark(compact: true),
                ),
                const Spacer(),
                IconButton(
                  tooltip: context.tr('Notifications'),
                  onPressed: () => context.go('/notifications'),
                  icon: Badge(
                    isLabelVisible: session.unread > 0,
                    label: Text('${session.unread}'),
                    child: const Icon(Icons.notifications_none, size: 26),
                  ),
                ),
                const SizedBox(width: 4),
                InkWell(
                  customBorder: const CircleBorder(),
                  onTap: () => context.go('/profile'),
                  child: CircleAvatar(
                    radius: 21,
                    backgroundColor: Brand.yellow,
                    foregroundImage: photo == null
                        ? null
                        : NetworkImage(photo, headers: session.api.authHeaders),
                    child: Text(
                      initialsOf(session.user.str('full_name', '?')),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        color: Brand.ink,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class MemberDrawer extends StatelessWidget {
  const MemberDrawer({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final location = GoRouterState.of(context).matchedLocation;
    final other = session.locale == 'sw' ? 'en' : 'sw';

    return Drawer(
      backgroundColor: Brand.ink,
      shape: const RoundedRectangleBorder(),
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 8, 16),
              child: Row(
                children: [
                  const Expanded(child: BrandMark(inverse: true)),
                  IconButton(
                    tooltip: context.tr('Close menu'),
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close, color: Color(0xB3FFFFFF)),
                  ),
                ],
              ),
            ),
            const Divider(color: Color(0x1AFFFFFF), height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
                children: [
                  for (final section in _memberMenu.entries) ...[
                    if (section.key != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(12, 18, 12, 8),
                        child: Text(
                          context.tr(section.key!).toUpperCase(),
                          style: const TextStyle(
                            color: Color(0x80FFFFFF),
                            fontWeight: FontWeight.w800,
                            fontSize: 12,
                            letterSpacing: 1.2,
                          ),
                        ),
                      ),
                    for (final item in section.value.where(
                      (item) => !(item.minorHidden && session.isMinor),
                    ))
                      _DrawerLink(
                        item: item,
                        active:
                            location == item.route ||
                            location.startsWith('${item.route}/'),
                        badge: item.route == '/notifications'
                            ? session.unread
                            : 0,
                      ),
                  ],
                ],
              ),
            ),
            const Divider(color: Color(0x1AFFFFFF), height: 1),
            ListTile(
              leading: const Icon(Icons.language, color: Brand.yellow),
              title: Text(
                other == 'en' ? 'English' : 'Kiswahili',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
              onTap: () => session.setLocale(other),
            ),
            Container(
              margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
              decoration: BoxDecoration(
                color: const Color(0x14FFFFFF),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 20,
                    backgroundColor: Brand.yellow,
                    child: Text(
                      initialsOf(session.user.str('full_name', '?')),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        color: Brand.ink,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          session.user.str('full_name'),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          session.user.str('membership_id'),
                          style: const TextStyle(
                            color: Color(0x99FFFFFF),
                            fontFamily: 'IBMPlexMono',
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: context.tr('Sign out'),
                    icon: const Icon(Icons.logout, color: Color(0xB3FFFFFF)),
                    onPressed: () async {
                      Navigator.pop(context);
                      await session.logout();
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DrawerLink extends StatelessWidget {
  const _DrawerLink({required this.item, required this.active, this.badge = 0});

  final _NavItem item;
  final bool active;
  final int badge;

  @override
  Widget build(BuildContext context) {
    final color = active ? Brand.ink : const Color(0xD9FFFFFF);
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Material(
        color: active ? Brand.yellow : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () {
            Navigator.pop(context);
            context.go(item.route);
          },
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
            child: Row(
              children: [
                Icon(
                  item.icon,
                  size: 22,
                  color: active ? Brand.ink : Brand.yellow,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    context.tr(item.label),
                    style: TextStyle(
                      color: color,
                      fontWeight: FontWeight.w700,
                      fontSize: 16,
                    ),
                  ),
                ),
                if (badge > 0)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: active ? Brand.ink : Brand.yellow,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$badge',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 12,
                        color: active ? Brand.yellow : Brand.ink,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Scrollable member page body with the website's padding.
class MemberPage extends StatelessWidget {
  const MemberPage({
    super.key,
    required this.children,
    this.onRefresh,
    this.bottomPadding = 32,
  });

  final List<Widget> children;
  final Future<void> Function()? onRefresh;
  final double bottomPadding;

  @override
  Widget build(BuildContext context) {
    final list = ListView(
      padding: EdgeInsets.fromLTRB(
        16,
        28,
        16,
        bottomPadding + MediaQuery.of(context).padding.bottom,
      ),
      physics: const AlwaysScrollableScrollPhysics(),
      children: children,
    );
    return onRefresh == null
        ? list
        : RefreshIndicator(onRefresh: onRefresh!, child: list);
  }
}

/// Kiswahili / English toggle.
class LanguageSwitch extends StatelessWidget {
  const LanguageSwitch({super.key, required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return SegmentedButton<String>(
      showSelectedIcon: false,
      style: SegmentedButton.styleFrom(
        backgroundColor: Colors.white,
        selectedBackgroundColor: Brand.ink,
        selectedForegroundColor: Colors.white,
        visualDensity: VisualDensity.compact,
      ),
      segments: const [
        ButtonSegment(value: 'sw', label: Text('Kiswahili')),
        ButtonSegment(value: 'en', label: Text('English')),
      ],
      selected: {session.locale},
      onSelectionChanged: (value) => session.setLocale(value.first),
    );
  }
}
