import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/labels.dart';
import '../../core/shells.dart';
import '../../core/theme.dart';
import '../../core/web_ui.dart';
import '../../core/widgets.dart';

/// "Community" (livewire/member/community/index.blade.php).
class SpacesScreen extends StatelessWidget {
  const SpacesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<List<Map<String, dynamic>>>(
        load: () async => listOf(await context.api.get('community/spaces')),
        builder: (context, spaces, reload) => MemberPage(
          onRefresh: reload,
          children: [
            PageTitle(
              context.tr('Community'),
              subtitle: context.tr(
                'Discuss taxes, share ideas and work together with Tax Club members. Moderators keep it safe.',
              ),
            ),
            const SizedBox(height: 24),
            const CommunityRules(),
            const SizedBox(height: 24),
            if (spaces.isEmpty)
              MistBox(
                context.tr('No discussion spaces yet.'),
                color: Colors.white,
              ),
            for (final space in spaces)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: WebCard(
                  onTap: () => context.push(
                    '/community/${space.str('slug')}',
                    extra: space,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Tag(
                            labelOf(context, 'space_types', space.str('type')),
                          ),
                          if (space.str('status') == 'locked') ...[
                            const SizedBox(width: 8),
                            Tag(context.tr('Read-only'), color: Brand.mist),
                          ],
                        ],
                      ),
                      const SizedBox(height: 12),
                      Text(
                        space.str('name'),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (space.strOrNull('description') != null) ...[
                        const SizedBox(height: 6),
                        Text(
                          space.str('description'),
                          style: const TextStyle(
                            color: Brand.slate,
                            fontSize: 16,
                            height: 1.5,
                          ),
                        ),
                      ],
                      const SizedBox(height: 10),
                      Text(
                        context.trPlural(
                          ':count post|:count posts',
                          space.integer('posts_count'),
                        ),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// The yellow "Community rules" box.
class CommunityRules extends StatelessWidget {
  const CommunityRules({super.key, this.initiallyExpanded = true});

  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Brand.yellowWash,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Brand.ink, width: 2),
      ),
      child: ExpansionTile(
        initiallyExpanded: initiallyExpanded,
        shape: const Border(),
        collapsedShape: const Border(),
        tilePadding: const EdgeInsets.symmetric(horizontal: 18),
        childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
        title: Text(
          context.tr('Community rules'),
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
        ),
        expandedCrossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Bullets([
            context.tr('Be kind and respectful. No bullying, insults or hate.'),
            context.tr(
              'Never share phone numbers, emails, addresses or social media names. Posts with contact details wait for a moderator.',
            ),
            context.tr('Do not ask anyone to meet you or move to another app.'),
            context.tr(
              'Share correct tax information. If you are not sure, Ask TRA.',
            ),
            context.tr(
              'See something wrong? Press Report. Moderators will act.',
            ),
          ]),
        ],
      ),
    );
  }
}

/// A space and its posts (livewire/member/community/space.blade.php).
class SpaceScreen extends StatefulWidget {
  const SpaceScreen({super.key, required this.slug, this.space});

  final String slug;
  final Map<String, dynamic>? space;

  @override
  State<SpaceScreen> createState() => _SpaceScreenState();
}

class _SpaceScreenState extends State<SpaceScreen> {
  final _list = GlobalKey<PagedListState<Map<String, dynamic>>>();
  final _title = TextEditingController();
  final _body = TextEditingController();

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _post() async {
    if (_body.text.trim().isEmpty) return;
    final result = await runAction(
      context,
      () => context.api.post('community/spaces/${widget.slug}/posts', {
        'title': _title.text.trim(),
        'body': _body.text.trim(),
      }),
      showServerMessage: true,
    );
    if (result != null) {
      _title.clear();
      _body.clear();
      _list.currentState?.refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final space = widget.space ?? const {};
    final problem = space.strOrNull('posting_problem');

    return MemberScaffold(
      body: PagedList<Map<String, dynamic>>(
        key: _list,
        padding: const EdgeInsets.fromLTRB(16, 28, 16, 40),
        separator: 12,
        load: (page) => context.api.paged(
          'community/spaces/${widget.slug}/posts',
          (row) => row,
          query: {'page': page},
        ),
        header: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PageTitle(
              space.str('name', context.tr('Community')),
              subtitle: space.strOrNull('description'),
              back: context.tr('Community'),
              onBack: () => context.go('/community'),
            ),
            const SizedBox(height: 22),
            if (problem != null)
              MistBox(problem, color: Colors.white)
            else
              WebCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      context.tr('Your post'),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 17,
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _title,
                      maxLength: 200,
                      decoration: InputDecoration(
                        hintText: context.tr('Title (optional)'),
                        counterText: '',
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _body,
                      minLines: 3,
                      maxLines: 8,
                      maxLength: 5000,
                      decoration: InputDecoration(
                        hintText: context.tr(
                          'Share an idea or ask the club something…',
                        ),
                      ),
                    ),
                    Text(
                      context.tr(
                        'Do not share phone numbers or other contact details.',
                      ),
                      style: const TextStyle(color: Brand.slate, fontSize: 14),
                    ),
                    const SizedBox(height: 12),
                    Align(
                      alignment: Alignment.centerRight,
                      child: WebButton(
                        context.tr('Post'),
                        expand: false,
                        onPressed: _post,
                      ),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 10),
          ],
        ),
        empty: MistBox(
          context.tr('No posts yet. Start the conversation!'),
          color: Colors.white,
          center: true,
        ),
        itemBuilder: (context, post, refresh) => PostCard(
          post: post,
          onTap: () async {
            await context.push('/community/post/${post.str('post_id')}');
            refresh();
          },
          onChanged: refresh,
        ),
      ),
    );
  }
}

class PostCard extends StatelessWidget {
  const PostCard({
    super.key,
    required this.post,
    this.onTap,
    this.onChanged,
    this.isComment = false,
  });

  final Map<String, dynamic> post;
  final VoidCallback? onTap;
  final VoidCallback? onChanged;
  final bool isComment;

  @override
  Widget build(BuildContext context) {
    final mine = post.flag('is_mine');
    return WebCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(20, 16, 8, 18),
      color: isComment ? const Color(0xFFFCFCFA) : Colors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 17,
                backgroundColor: Brand.yellow,
                child: Text(
                  initialsOf(post.str('author', '?')),
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                    color: Brand.ink,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      post.str('author'),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    Text(
                      context.ago(post.date('created_at')),
                      style: const TextStyle(fontSize: 13, color: Brand.slate),
                    ),
                  ],
                ),
              ),
              if (post.str('status') == 'held')
                Tag(
                  context.tr('Waiting for a moderator'),
                  color: const Color(0xFFFDE68A),
                ),
              PopupMenuButton<String>(
                onSelected: (action) async {
                  if (action == 'delete') {
                    if (!await confirm(
                      context,
                      context.tr(
                        isComment
                            ? 'Delete this comment?'
                            : 'Delete this post?',
                      ),
                      action: context.tr('Delete'),
                      danger: true,
                    )) {
                      return;
                    }
                    if (!context.mounted) return;
                    final result = await runAction(
                      context,
                      () => context.api.delete(
                        'community/posts/${post.str('post_id')}',
                      ),
                      showServerMessage: true,
                    );
                    if (result != null) onChanged?.call();
                  } else {
                    await showReportSheet(
                      context,
                      type: 'post',
                      id: post.str('post_id'),
                    );
                  }
                },
                itemBuilder: (context) => [
                  if (mine)
                    PopupMenuItem(
                      value: 'delete',
                      child: Text(context.tr('Delete')),
                    ),
                  if (!mine)
                    PopupMenuItem(
                      value: 'report',
                      child: Text(context.tr('Report')),
                    ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (post.strOrNull('title') != null) ...[
                  Text(
                    post.str('title'),
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 18,
                    ),
                  ),
                  const SizedBox(height: 4),
                ],
                Text(
                  post.str('body'),
                  maxLines: onTap == null ? null : 6,
                  overflow: onTap == null ? null : TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 16, height: 1.5),
                ),
                if (!isComment && onTap != null) ...[
                  const SizedBox(height: 12),
                  LinkText(
                    context.trPlural(
                      '{0} Comment|{1} :count comment|[2,*] :count comments',
                      post.integer('comments_count'),
                    ),
                    onTap: onTap,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A post and its comments (livewire/member/community/post.blade.php).
class PostScreen extends StatefulWidget {
  const PostScreen({super.key, required this.id});

  final String id;

  @override
  State<PostScreen> createState() => _PostScreenState();
}

class _PostScreenState extends State<PostScreen> {
  final _loader = GlobalKey<LoaderState<Map<String, dynamic>>>();
  final _comment = TextEditingController();

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final body = _comment.text.trim();
    if (body.isEmpty) return;
    final result = await runAction(
      context,
      () => context.api.post('community/posts/${widget.id}/comments', {
        'body': body,
      }),
      showServerMessage: true,
    );
    if (result != null) {
      _comment.clear();
      _loader.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return MemberScaffold(
      body: Loader<Map<String, dynamic>>(
        key: _loader,
        load: () async =>
            ((await context.api.get('community/posts/${widget.id}')) as Map)
                .cast<String, dynamic>(),
        builder: (context, post, reload) => MemberPage(
          onRefresh: reload,
          children: [
            LinkText(
              context.tr('Community'),
              onTap: () => context.go('/community'),
            ),
            const SizedBox(height: 18),
            PostCard(post: post, onChanged: () => context.pop()),
            const SizedBox(height: 24),
            SectionHeading(
              context.trPlural(
                '{0} Comments|{1} :count comment|[2,*] :count comments',
                post.list('comments').length,
              ),
              size: 20,
            ),
            const SizedBox(height: 12),
            for (final comment in post.list('comments'))
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: PostCard(
                  post: comment,
                  isComment: true,
                  onChanged: reload,
                ),
              ),
            const SizedBox(height: 12),
            WebCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    context.tr('Add a comment'),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _comment,
                    minLines: 2,
                    maxLines: 6,
                    maxLength: 3000,
                    decoration: const InputDecoration(counterText: ''),
                  ),
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.centerRight,
                    child: WebButton(
                      context.tr('Comment'),
                      expand: false,
                      onPressed: _send,
                    ),
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

/// Report content or a person to the safety team (SAF-REQ01).
Future<void> showReportSheet(
  BuildContext context, {
  required String type,
  required String id,
}) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _ReportSheet(type: type, id: id),
  );
}

class _ReportSheet extends StatefulWidget {
  const _ReportSheet({required this.type, required this.id});

  final String type;
  final String id;

  @override
  State<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends State<_ReportSheet> {
  final _details = TextEditingController();
  String? _category;

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final categories = optionsOf(context, 'report_categories');
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        0,
        16,
        MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.tr('Report'),
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              context.tr(
                'Your report is private. The person will not know who reported them.',
              ),
              style: const TextStyle(color: Brand.slate),
            ),
            const SizedBox(height: 8),
            RadioGroup<String>(
              groupValue: _category,
              onChanged: (value) => setState(() => _category = value),
              child: Column(
                children: [
                  for (final category in categories.entries)
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      value: category.key,
                      title: Text(
                        category.value,
                        style: const TextStyle(fontSize: 15),
                      ),
                    ),
                ],
              ),
            ),
            TextField(
              controller: _details,
              maxLines: 3,
              maxLength: 2000,
              decoration: InputDecoration(labelText: context.tr('Details')),
            ),
            const SizedBox(height: 8),
            WebButton(
              context.tr('Report'),
              onPressed: _category == null
                  ? null
                  : () async {
                      final result = await runAction(
                        context,
                        () => context.api.post('reports', {
                          'type': widget.type,
                          'id': widget.id,
                          'category': _category,
                          'details': _details.text.trim(),
                        }),
                        showServerMessage: true,
                      );
                      if (result != null && context.mounted) {
                        Navigator.pop(context);
                      }
                    },
            ),
            const SizedBox(height: 8),
            Text(
              context.tr(
                'If someone is in danger right now, tell your patron, a teacher or a parent straight away.',
              ),
              style: const TextStyle(color: Brand.slate, fontSize: 13),
            ),
          ],
        ),
      ),
    );
  }
}
