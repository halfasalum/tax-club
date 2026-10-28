// src/pages/landing/LandingPage.tsx
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion, useInView } from 'framer-motion';
import {
  Box,
  Container,
  Title,
  Text,
  Button,
  Group,
  Stack,
  SimpleGrid,
  Card,
  Badge,
  Center,
  Loader,
  ThemeIcon,
  Avatar,
  Table,
} from '@mantine/core';
import {
  IconUsers,
  IconBook2,
  IconCertificate,
  IconVideo,
  IconArticle,
  IconInfinity,
  IconCalendarEvent,
  IconArrowRight,
  IconTrophy,
  IconPuzzle,
  IconGift,
  IconSpeakerphone,
  IconBell,
  IconUsersGroup,
  IconMedal,
  IconChevronDown,
  IconCrown,
  IconRocket,
  IconSparkles,
} from '@tabler/icons-react';
import { Logo } from '../../components/common/Logo';
import { TierBadge, type MembershipTier } from '../../components/common/TierBadge';
import { apiClient } from '../../api/apiClient';
import './LandingPage.css';

interface Highlights {
  stats: { members: number; content: number; quizzes: number };
  latest_content: Array<{ content_id: string; title: string; content_type: string; published_at: string }>;
  upcoming_events: Array<{ event_id: string; title: string; location: string | null; starts_at: string }>;
}

interface LeaderboardRow {
  rank: number;
  user_id: string;
  full_name: string;
  membership_id: string;
  total_points: number;
  tier: MembershipTier;
}

const medalColor = (rank: number) => (rank === 1 ? 'traGold' : rank === 2 ? 'gray' : rank === 3 ? 'orange' : 'traDark');

const typeIcon = (type: string) => {
  switch (type) {
    case 'video': return <IconVideo size={16} />;
    case 'infographic': return <IconInfinity size={16} />;
    case 'quiz': return <IconCertificate size={16} />;
    default: return <IconArticle size={16} />;
  }
};

const typeAccent = (type: string) => {
  switch (type) {
    case 'video': return 'red';
    case 'infographic': return 'green';
    case 'quiz': return 'traGold';
    default: return 'blue';
  }
};

// ─── Scroll-reveal wrapper ─────────────────────────────────────────
const Reveal = ({ children, delay = 0 }: { children: ReactNode; delay?: number }) => (
  <motion.div
    initial={{ opacity: 0, y: 28 }}
    whileInView={{ opacity: 1, y: 0 }}
    viewport={{ once: true, margin: '-80px' }}
    transition={{ duration: 0.6, delay, ease: 'easeOut' }}
  >
    {children}
  </motion.div>
);

// ─── Count-up number ───────────────────────────────────────────────
const AnimatedCounter = ({ value, suffix = '+' }: { value: number; suffix?: string }) => {
  const ref = useRef<HTMLSpanElement>(null);
  const isInView = useInView(ref, { once: true, margin: '-40px' });
  const [display, setDisplay] = useState(0);

  useEffect(() => {
    if (!isInView) return;
    let start: number | null = null;
    const duration = 1100;
    let frame: number;

    const step = (ts: number) => {
      if (start === null) start = ts;
      const progress = Math.min((ts - start) / duration, 1);
      setDisplay(Math.floor(progress * value));
      if (progress < 1) frame = requestAnimationFrame(step);
    };

    frame = requestAnimationFrame(step);
    return () => cancelAnimationFrame(frame);
  }, [isInView, value]);

  return <span ref={ref}>{display}{value > 0 ? suffix : ''}</span>;
};

export const LandingPage = () => {
  const navigate = useNavigate();
  const [data, setData] = useState<Highlights | null>(null);
  const [loading, setLoading] = useState(true);
  const [topMembers, setTopMembers] = useState<LeaderboardRow[]>([]);

  useEffect(() => {
    apiClient
      .get('/public/highlights')
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));

    apiClient
      .get('/leaderboard', { params: { per_page: 5 } })
      .then((res) => setTopMembers(res.data?.leaderboard || []))
      .catch(() => setTopMembers([]));
  }, []);

  const scrollToContent = () => {
    document.getElementById('landing-content')?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <Box>
      {/* Top nav */}
      <Box
        style={{
          position: 'sticky',
          top: 0,
          zIndex: 100,
          backgroundColor: 'rgba(10, 10, 10, 0.85)',
          backdropFilter: 'blur(10px)',
          borderBottom: '3px solid var(--mantine-color-traGold-5)',
        }}
      >
        <Container size="lg" py="sm">
          <Group justify="space-between">
            <Logo onDark />
            <Group>
              <Button variant="subtle" color="gray.3" component={Link} to="/auth/signin">
                Sign In
              </Button>
              <Button color="traGold" component={Link} to="/auth/register">
                Get Started
              </Button>
            </Group>
          </Group>
        </Container>
      </Box>

      {/* Hero */}
      <Box
        className="dot-grid-bg"
        style={{
          position: 'relative',
          background: 'linear-gradient(135deg, var(--mantine-color-traDark-8) 0%, var(--mantine-color-traDark-9) 100%)',
          color: 'white',
        }}
      >
        <div className="glow-blob glow-blob-gold" />
        <div className="glow-blob glow-blob-gold-2" />

        <Container size="lg" py={100} style={{ position: 'relative', zIndex: 1 }}>
          <Stack align="center" gap="md" ta="center">
            <motion.div
              initial={{ opacity: 0, y: -12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5 }}
            >
              <Badge size="lg" color="traGold" variant="filled" leftSection={<IconSparkles size={14} />}>
                Tanzania Revenue Authority
              </Badge>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.1 }}
            >
              <Title order={1} fz={{ base: 34, sm: 56 }} maw={760} lh={1.1}>
                Learn Tax. Earn Points.{' '}
                <Box component="span" className="gold-gradient-text">Build Your Future.</Box>
              </Title>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
            >
              <Text size="lg" c="gray.3" maw={620}>
                The TRA Tax Club is a free education hub for students — video lessons, articles, and quizzes
                that build real tax literacy, with a leaderboard and rewards that celebrate your progress.
              </Text>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.3 }}
            >
              <Group mt="md">
                <Button
                  size="lg"
                  color="traGold"
                  rightSection={<IconArrowRight size={18} />}
                  onClick={() => navigate('/auth/register')}
                >
                  Create Free Account
                </Button>
                <Button size="lg" variant="outline" color="gray.3" component={Link} to="/auth/signin">
                  Sign In
                </Button>
              </Group>
            </motion.div>
          </Stack>
        </Container>

        <Box
          className="scroll-cue"
          onClick={scrollToContent}
          style={{ position: 'relative', zIndex: 1, textAlign: 'center', paddingBottom: 28, cursor: 'pointer' }}
        >
          <IconChevronDown size={26} color="var(--mantine-color-traGold-4)" />
        </Box>

        {/* Wave divider into the body background */}
        <Box style={{ position: 'absolute', bottom: -1, left: 0, width: '100%', lineHeight: 0, zIndex: 1 }}>
          <svg viewBox="0 0 1440 60" style={{ width: '100%', height: 48, display: 'block' }} preserveAspectRatio="none">
            <path
              fill="var(--mantine-color-body)"
              d="M0,32 C240,64 480,0 720,20 C960,40 1200,72 1440,28 L1440,60 L0,60 Z"
            />
          </svg>
        </Box>
      </Box>

      <div id="landing-content" />

      {loading ? (
        <Center py={60}><Loader color="traGold" /></Center>
      ) : (
        <>
          {/* Stats */}
          <Container size="lg" py={60}>
            <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="lg">
              {[
                { icon: IconUsers, value: data?.stats.members ?? 0, label: 'Registered Members' },
                { icon: IconBook2, value: data?.stats.content ?? 0, label: 'Learning Resources' },
                { icon: IconCertificate, value: data?.stats.quizzes ?? 0, label: 'Quizzes Available' },
              ].map((stat, idx) => (
                <Reveal key={stat.label} delay={idx * 0.1}>
                  <Card withBorder radius="md" padding="lg" className="hover-lift">
                    <Group>
                      <ThemeIcon size={52} radius="xl" variant="gradient" gradient={{ from: 'traGold.3', to: 'traGold.6', deg: 135 }}>
                        <stat.icon size={26} color="var(--mantine-color-traDark-9)" />
                      </ThemeIcon>
                      <div>
                        <Text fw={700} size="xl"><AnimatedCounter value={stat.value} /></Text>
                        <Text c="dimmed" size="sm">{stat.label}</Text>
                      </div>
                    </Group>
                  </Card>
                </Reveal>
              ))}
            </SimpleGrid>
          </Container>

          {/* Latest updates */}
          {data && data.latest_content.length > 0 && (
            <Box className="alt-section" py={60}>
              <Container size="lg">
                <Reveal>
                  <Title order={2} mb="md">Latest Updates</Title>
                </Reveal>
                <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md">
                  {data.latest_content.map((item, idx) => (
                    <Reveal key={item.content_id} delay={idx * 0.06}>
                      <Card withBorder radius="md" padding="lg" className="hover-lift" style={{ borderTop: `3px solid var(--mantine-color-${typeAccent(item.content_type)}-5)` }}>
                        <Badge color="traGold" leftSection={typeIcon(item.content_type)} mb="xs">
                          {item.content_type}
                        </Badge>
                        <Text fw={600} lineClamp={2}>{item.title}</Text>
                        <Text size="xs" c="dimmed" mt={4}>
                          {new Date(item.published_at).toLocaleDateString()}
                        </Text>
                      </Card>
                    </Reveal>
                  ))}
                </SimpleGrid>
                <Group justify="center" mt="lg">
                  <Button variant="light" color="traGold" onClick={() => navigate('/auth/register')}>
                    Join free to explore the full Education Hub
                  </Button>
                </Group>
              </Container>
            </Box>
          )}

          {/* Gamification: Games & Puzzles */}
          <Box py={60}>
            <Container size="lg">
              <Reveal>
                <Stack align="center" gap={4} mb="xl" ta="center">
                  <Badge color="traGold" variant="light" size="lg">Gamification</Badge>
                  <Title order={2}>Learn Tax Through Games, Quizzes & Competitions</Title>
                  <Text c="dimmed" maw={600}>
                    Sharpen your tax knowledge, climb the leaderboard, and get recognized for it.
                  </Text>
                </Stack>
              </Reveal>

              <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }} spacing="md" mb="xl">
                {[
                  { icon: IconPuzzle, title: 'Quizzes & Challenges', description: 'Bite-sized tax quizzes and puzzles that turn learning into a game.' },
                  { icon: IconTrophy, title: 'Weekly & Monthly Competitions', description: 'Regular challenges give every member a fresh shot at the top spot.' },
                  { icon: IconMedal, title: 'Leaderboards', description: 'Earn points for every quiz you pass and see your rank update live.' },
                  { icon: IconGift, title: 'Rewards', description: 'Top performers earn airtime, certificates, merchandise and public recognition.' },
                ].map((item, idx) => (
                  <Reveal key={item.title} delay={idx * 0.08}>
                    <GamificationCard {...item} />
                  </Reveal>
                ))}
              </SimpleGrid>

              <Reveal delay={0.2}>
                <Card withBorder radius="md" padding="lg" maw={640} mx="auto" className="hover-lift">
                  <Group justify="space-between" mb="md">
                    <Group gap="xs">
                      <IconTrophy size={20} color="var(--mantine-color-traGold-6)" />
                      <Text fw={700}>Top Members This Season</Text>
                    </Group>
                    <Text size="xs" c="dimmed">By total points</Text>
                  </Group>

                  {topMembers.length === 0 ? (
                    <Text c="dimmed" size="sm" ta="center" py="md">
                      Rankings will appear here once members start earning points.
                    </Text>
                  ) : (
                    <Table verticalSpacing="sm">
                      <Table.Tbody>
                        {topMembers.map((row) => (
                          <Table.Tr key={row.user_id}>
                            <Table.Td w={44}>
                              <Avatar
                                radius="xl"
                                size="sm"
                                color={medalColor(row.rank)}
                                variant="filled"
                                className={row.rank === 1 ? 'rank-glow-1' : undefined}
                              >
                                {row.rank === 1 ? <IconCrown size={14} /> : row.rank}
                              </Avatar>
                            </Table.Td>
                            <Table.Td>
                              <Text fw={500} size="sm">{row.full_name}</Text>
                            </Table.Td>
                            <Table.Td><TierBadge tier={row.tier} size="sm" /></Table.Td>
                            <Table.Td ta="right">
                              <Text fw={700} size="sm">{row.total_points} pts</Text>
                            </Table.Td>
                          </Table.Tr>
                        ))}
                      </Table.Tbody>
                    </Table>
                  )}

                  <Group justify="center" mt="md">
                    <Button variant="light" color="traGold" size="sm" onClick={() => navigate('/auth/register')}>
                      Join the leaderboard
                    </Button>
                  </Group>
                </Card>
              </Reveal>
            </Container>
          </Box>

          {/* Events & Activities */}
          <Box className="alt-section" py={60}>
            <Container size="lg">
              <Reveal>
                <Stack align="center" gap={4} mb="xl" ta="center">
                  <Badge color="traGold" variant="light" size="lg">Events & Activities</Badge>
                  <Title order={2}>Stay Connected to the Tax Club</Title>
                  <Text c="dimmed" maw={600}>
                    Get notified about Tax Club events, tax awareness campaigns, competitions, workshops and seminars.
                  </Text>
                </Stack>
              </Reveal>

              <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="md" mb="xl">
                {[
                  { icon: IconBell, title: 'Event Notifications', description: 'Never miss a Tax Club event — updates delivered straight to your dashboard.' },
                  { icon: IconSpeakerphone, title: 'Tax Awareness Campaigns', description: 'Community drives that spread tax literacy beyond the classroom.' },
                  { icon: IconUsersGroup, title: 'Workshops & Seminars', description: 'Hands-on competitions, workshops and seminars led by TRA educators.' },
                ].map((item, idx) => (
                  <Reveal key={item.title} delay={idx * 0.08}>
                    <GamificationCard {...item} />
                  </Reveal>
                ))}
              </SimpleGrid>

              {data && data.upcoming_events.length > 0 ? (
                <Stack gap="sm">
                  {data.upcoming_events.map((event, idx) => (
                    <Reveal key={event.event_id} delay={idx * 0.06}>
                      <Card withBorder radius="md" padding="md" className="hover-lift">
                        <Group justify="space-between">
                          <Group gap="sm">
                            <IconCalendarEvent size={22} color="var(--mantine-color-traGold-6)" />
                            <div>
                              <Text fw={600}>{event.title}</Text>
                              <Text size="sm" c="dimmed">{event.location || 'Online'}</Text>
                            </div>
                          </Group>
                          <Text size="sm" fw={500}>
                            {new Date(event.starts_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                          </Text>
                        </Group>
                      </Card>
                    </Reveal>
                  ))}
                </Stack>
              ) : (
                <Text c="dimmed" size="sm" ta="center">
                  No events scheduled right now — sign up to be notified when the next one is announced.
                </Text>
              )}
            </Container>
          </Box>

          {/* Final CTA banner */}
          <Box className="cta-banner" py={70}>
            <Container size="lg" style={{ position: 'relative', zIndex: 1 }}>
              <Reveal>
                <Stack align="center" gap="sm" ta="center">
                  <ThemeIcon size={56} radius="xl" color="traGold" variant="filled">
                    <IconRocket size={28} />
                  </ThemeIcon>
                  <Title order={2} c="white">Ready to Start Earning?</Title>
                  <Text c="gray.3" maw={520}>
                    Join for free, take your first quiz, and see your name climb the leaderboard.
                  </Text>
                  <Button
                    size="xl"
                    color="traGold"
                    mt="sm"
                    rightSection={<IconArrowRight size={20} />}
                    onClick={() => navigate('/auth/register')}
                  >
                    Create Your Free Account
                  </Button>
                </Stack>
              </Reveal>
            </Container>
          </Box>
        </>
      )}

      {/* Footer */}
      <Box style={{ backgroundColor: 'var(--mantine-color-traDark-9)' }} py="xl">
        <Container size="lg">
          <Group justify="space-between">
            <Logo onDark size="sm" />
            <Text c="gray.5" size="sm">© {new Date().getFullYear()} Tanzania Revenue Authority. All rights reserved.</Text>
          </Group>
        </Container>
      </Box>
    </Box>
  );
};

const GamificationCard = ({
  icon: Icon,
  title,
  description,
}: {
  icon: any;
  title: string;
  description: string;
}) => (
  <Card withBorder radius="md" padding="lg" className="hover-lift" h="100%">
    <ThemeIcon size={44} radius="md" variant="gradient" gradient={{ from: 'traGold.3', to: 'traGold.6', deg: 135 }} mb="sm">
      <Icon size={24} color="var(--mantine-color-traDark-9)" />
    </ThemeIcon>
    <Text fw={600} mb={4}>{title}</Text>
    <Text size="sm" c="dimmed">{description}</Text>
  </Card>
);
