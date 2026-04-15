// src/components/navigation/SidebarNav.tsx
import { NavLink as RouterNavLink, useLocation } from 'react-router-dom';
import { NavLink, Stack, Text, Tooltip, ScrollArea, Divider } from '@mantine/core';
import {
    IconUsers,
    IconUserShield,
    IconBuilding,
    IconBook,
    IconFileText,
    IconBriefcase,
    IconMessageCircle,
    IconCalendarEvent,
    IconLayoutDashboard,
    IconSettings,
    IconCertificate,
    IconListCheck,
    IconSchool,
    IconVideo,
    IconArticle,
    IconChartBar,
    IconPlus
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';

interface NavItem {
    label: string;
    icon: React.ElementType;
    to: string;
    permission?: string;
    children?: NavItem[];
}

export const SidebarNav = () => {
    const location = useLocation();
    const { hasPermission } = useAuth();

    const navItems: NavItem[] = [
        {
            label: 'Dashboard',
            icon: IconLayoutDashboard,
            to: '/dashboard',
        },
        {
            label: 'Users Management',
            icon: IconUsers,
            to: '/users',
            permission: 'view_users',
            children: [
                { label: 'All Users', icon: IconUsers, to: '/users', permission: 'view_users' },
                { label: 'Add User', icon: IconUserShield, to: '/users/create', permission: 'add_user' },
                { label: 'Roles', icon: IconUserShield, to: '/roles', permission: 'view_roles' },
            ],
        },
        {
            label: 'Modules & Permissions',
            icon: IconSettings,
            to: '/modules',
            permission: 'view_modules',
            children: [
                { label: 'All Modules', icon: IconListCheck, to: '/modules', permission: 'view_modules' },
            ],
        },
        {
            label: 'Institutions',
            icon: IconBuilding,
            to: '/institutions',
            permission: 'add_institution', // Or 'view_institutions' if you have it
        },
        {
            label: 'Content Management',
            icon: IconBook,
            to: '/content',
            permission: 'upload_content',
            children: [
                { label: 'All Content', icon: IconArticle, to: '/content', permission: 'upload_content' },
                { label: 'Upload Content', icon: IconPlus, to: '/content/create', permission: 'upload_content' },
            ],
        },
        {
            label: 'Quiz Management',
            icon: IconCertificate,
            to: '/quizzes',
            permission: 'create_quiz',
            children: [
                { label: 'All Quizzes', icon: IconListCheck, to: '/quizzes', permission: 'view_quizzes' },
                { label: 'Create Quiz', icon: IconPlus, to: '/quizzes/create', permission: 'create_quiz' },
            ],
        },
        {
            label: 'Opportunities',
            icon: IconBriefcase,
            to: '/opportunities',
            permission: 'add_opportunity',
            children: [
                { label: 'All Opportunities', icon: IconBriefcase, to: '/opportunities', permission: 'view_opportunities' },
                { label: 'Add Opportunity', icon: IconBriefcase, to: '/opportunities/create', permission: 'add_opportunity' },
            ],
        },
        {
            label: 'Forum',
            icon: IconMessageCircle,
            to: '/forum',
            permission: 'create_forum_post',
        },
        {
            label: 'Events',
            icon: IconCalendarEvent,
            to: '/events',
            permission: 'add_event',
            children: [
                { label: 'All Events', icon: IconCalendarEvent, to: '/events', permission: 'view_events' },
                { label: 'Create Event', icon: IconCalendarEvent, to: '/events/create', permission: 'add_event' },
                { label: 'Attendees', icon: IconUsers, to: '/events/attendees', permission: 'view_event_attendees' },
            ],
        },
    ];

    const renderNavItems = (items: NavItem[], level: number = 0) => {
        return items.map((item) => {
            // Check permission if required
            if (item.permission && !hasPermission(item.permission)) {
                return null;
            }

            const hasChildren = item.children && item.children.length > 0;
            const isActive = location.pathname === item.to || location.pathname.startsWith(item.to + '/');

            if (hasChildren) {
                const filteredChildren = item.children?.filter(child =>
                    !child.permission || hasPermission(child.permission)
                );

                if (!filteredChildren || filteredChildren.length === 0) return null;

                return (
                    <NavLink
                        key={item.to}
                        label={item.label}
                        leftSection={<item.icon size={18} />}
                        defaultOpened={isActive}
                        childrenOffset={28}
                        variant="subtle"
                    >
                        {renderNavItems(filteredChildren, level + 1)}
                    </NavLink>
                );
            }

            return (
                <NavLink
                    key={item.to}
                    component={RouterNavLink}
                    to={item.to}
                    label={item.label}
                    leftSection={<item.icon size={18} />}
                    active={location.pathname === item.to}
                    variant="subtle"
                />
            );
        });
    };

    return (
        <ScrollArea h="calc(100vh - 60px)" type="scroll">
            <Stack gap="xs" p="md">
                {renderNavItems(navItems)}
            </Stack>
        </ScrollArea>
    );
};