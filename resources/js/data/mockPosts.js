// Shared dummy data for the Posts listing and Post Preview pages.
// Both pages import from here so a post's id + platforms stay consistent
// when navigating between the list and its per-platform preview.

export const platformMeta = {
    facebook: {
        key: 'facebook',
        name: 'Facebook',
        icon: 'fab fa-facebook-f',
        color: '#1877F2',
        page: 'Your Business Page',
        handle: '@yourbusiness',
    },
    instagram: {
        key: 'instagram',
        name: 'Instagram',
        icon: 'fab fa-instagram',
        color: '#E1306C',
        page: 'yourbusiness',
        handle: '@yourbusiness',
    },
    x: {
        key: 'x',
        name: 'X',
        icon: 'fab fa-x-twitter',
        color: '#111827',
        page: 'Your Business',
        handle: '@yourbusiness',
    },
    linkedin: {
        key: 'linkedin',
        name: 'LinkedIn',
        icon: 'fab fa-linkedin-in',
        color: '#0A66C2',
        page: 'Your Business Inc.',
        handle: '1,204 followers',
    },
    tiktok: {
        key: 'tiktok',
        name: 'TikTok',
        icon: 'fab fa-tiktok',
        color: '#111827',
        page: 'yourbusiness',
        handle: '@yourbusiness',
    },
    youtube: {
        key: 'youtube',
        name: 'YouTube',
        icon: 'fab fa-youtube',
        color: '#FF0000',
        page: 'Your Business',
        handle: '12.4K subscribers',
    },
};

export const platformOrder = ['facebook', 'instagram', 'x', 'linkedin', 'tiktok', 'youtube'];

function platformsFor(keys) {
    return keys.map(key => platformMeta[key]);
}

// ---- Deterministic per-platform engagement (reactions, comments, replies) ----
// Same post + platform always generates the same numbers/comments, so the
// preview stays stable across renders and platform switches.

const commenterPool = [
    { name: 'Sarah Johnson', color: '#F59E0B' },
    { name: 'Michael Chen', color: '#3B82F6' },
    { name: 'Priya Patel', color: '#EC4899' },
    { name: 'David Okafor', color: '#10B981' },
    { name: 'Emma Wilson', color: '#8B5CF6' },
    { name: 'Liam Garcia', color: '#EF4444' },
    { name: 'Aisha Khan', color: '#14B8A6' },
    { name: 'James Wright', color: '#6366F1' },
];

const commentTexts = [
    'This looks amazing! 🔥',
    "Can't wait to get my hands on this.",
    'Where can I buy this?',
    'Great work, team! 👏',
    'Love the colors on this one.',
    'Is this available worldwide?',
    'Following since day one, keep it up!',
    'This is exactly what I needed, thank you!',
    'Quality content as always.',
    'Sharing this with my team right now.',
];

const replyTexts = [
    'Thank you so much! 🙏',
    'Yes! Shipping worldwide now.',
    'Link is in our bio, check it out!',
    'Glad you liked it!',
    'Appreciate the support 💙',
];

function seededRandom(seed) {
    let value = seed % 2147483647;
    if (value <= 0) value += 2147483646;
    return function next() {
        value = (value * 16807) % 2147483647;
        return (value - 1) / 2147483646;
    };
}

function pick(rand, arr) {
    return arr[Math.floor(rand() * arr.length)];
}

export const reactionKindsByPlatform = {
    facebook: [
        { key: 'like', icon: 'fas fa-thumbs-up', color: '#1877F2' },
        { key: 'love', icon: 'fas fa-heart', color: '#F33E58' },
        { key: 'haha', icon: 'fas fa-laugh', color: '#F7B928' },
        { key: 'wow', icon: 'fas fa-surprise', color: '#F7B928' },
        { key: 'sad', icon: 'fas fa-sad-tear', color: '#F7B928' },
    ],
    linkedin: [
        { key: 'like', icon: 'fas fa-thumbs-up', color: '#0A66C2' },
        { key: 'celebrate', icon: 'fas fa-award', color: '#6DAE4F' },
        { key: 'support', icon: 'fas fa-hand-holding-heart', color: '#8134AF' },
        { key: 'love', icon: 'fas fa-heart', color: '#DD2A7B' },
        { key: 'insightful', icon: 'fas fa-lightbulb', color: '#F5B800' },
    ],
    instagram: [
        { key: 'love', icon: 'fas fa-heart', color: '#E1306C' },
    ],
    x: [
        { key: 'like', icon: 'fas fa-heart', color: '#F91880' },
    ],
    tiktok: [
        { key: 'love', icon: 'fas fa-heart', color: '#FE2C55' },
    ],
    youtube: [
        { key: 'like', icon: 'fas fa-thumbs-up', color: '#FF0000' },
    ],
};

function buildEngagement(post, platformKey) {

    const seed = post.id * 31 + platformKey.split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0);
    const rand = seededRandom(seed);

    // A post that hasn't published yet (scheduled/failed/draft) can't have
    // real engagement — only generate dummy activity for published posts.
    const isPublished = post.status === 'Published';

    const reactionsTotal = isPublished ? (post.likes || Math.floor(rand() * 400) + 20) : 0;
    const kinds = reactionKindsByPlatform[platformKey] || reactionKindsByPlatform.facebook;
    const weights = [0.68, 0.15, 0.08, 0.06, 0.03].slice(0, kinds.length);
    const weightSum = weights.reduce((a, b) => a + b, 0);
    const reactions = kinds.map((kind, i) => ({
        ...kind,
        count: Math.max(0, Math.round(reactionsTotal * (weights[i] / weightSum))),
    }));

    const commentsCount = isPublished ? (post.comments || Math.floor(rand() * 30)) : 0;
    const topCommentCount = isPublished ? Math.min(4, Math.max(commentsCount ? 1 : 0, Math.round(commentsCount / 15))) : 0;

    const comments = [];
    for (let i = 0; i < topCommentCount; i++) {
        const commenter = pick(rand, commenterPool);
        const hasReply = rand() > 0.5;
        comments.push({
            id: i + 1,
            author: commenter.name,
            avatarColor: commenter.color,
            content: pick(rand, commentTexts),
            timeAgo: `${Math.floor(rand() * 22) + 1}h`,
            likes: Math.floor(rand() * 40),
            replies: hasReply ? [{
                author: platformMeta[platformKey].page,
                avatarColor: platformMeta[platformKey].color,
                content: pick(rand, replyTexts),
                timeAgo: `${Math.floor(rand() * 10) + 1}h`,
                likes: Math.floor(rand() * 15),
            }] : [],
        });
    }

    return {
        reactions,
        reactionsTotal,
        commentsCount,
        sharesCount: isPublished ? (post.shares || Math.floor(rand() * 60)) : 0,
        viewsCount: isPublished ? (post.views || Math.floor(rand() * 4000)) : 0,
        impressions: isPublished ? Math.round((post.views || 500) * (1.4 + rand())) : 0,
        bookmarks: isPublished ? Math.floor(rand() * 50) : 0,
        comments,
    };
}

// ---- Deterministic "live post" URLs, in each platform's real URL shape ----
// These are dummy targets (the posts don't really exist on these platforms
// yet) but are formatted the way a real published post's URL would look,
// so the "Open on Platform" link reads correctly.

function slugify(str) {
    return str.toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function pseudoDigits(seed, length) {
    const rand = seededRandom(seed);
    let out = String(Math.floor(rand() * 9) + 1);
    while (out.length < length) out += Math.floor(rand() * 10);
    return out;
}

function pseudoCode(seed, length) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const rand = seededRandom(seed);
    let out = '';
    for (let i = 0; i < length; i++) out += chars[Math.floor(rand() * chars.length)];
    return out;
}

function buildPlatformUrl(post, platformKey) {

    const seed = post.id * 97 + platformKey.length * 13;
    const slug = slugify(platformMeta[platformKey].page);

    switch (platformKey) {
        case 'facebook':
            return `https://www.facebook.com/${slug}/posts/${pseudoDigits(seed, 15)}`;
        case 'instagram':
            return `https://www.instagram.com/p/${pseudoCode(seed, 11)}/`;
        case 'x':
            return `https://x.com/${slug}/status/${pseudoDigits(seed, 18)}`;
        case 'linkedin':
            return `https://www.linkedin.com/feed/update/urn:li:activity:${pseudoDigits(seed, 19)}/`;
        case 'tiktok':
            return `https://www.tiktok.com/@${slug}/video/${pseudoDigits(seed, 19)}`;
        case 'youtube':
            return `https://www.youtube.com/watch?v=${pseudoCode(seed, 11)}`;
        default:
            return '#';
    }

}

const rawPosts = [
    {
        id: 1,
        type: 'image',
        platformKeys: ['facebook', 'instagram', 'linkedin'],
        status: 'Published',
        title: 'Summer Mega Sale',
        content: 'Enjoy up to 50% OFF on all electronics this weekend only.',
        image: 'https://picsum.photos/800/450?1',
        likes: 582, comments: 63, shares: 21, views: 1420,
        author: 'John Smith', created_at: '2026-07-26',
    },
    {
        id: 2,
        type: 'video',
        platformKeys: ['instagram', 'facebook'],
        status: 'Published',
        title: 'Summer Collection Reel',
        content: 'Watch our newest fashion collection.',
        thumbnail: 'https://picsum.photos/800/450?22',
        video: 'https://www.w3schools.com/html/mov_bbb.mp4',
        likes: 1450, comments: 84, shares: 42, views: 8620,
        author: 'Sarah', created_at: '2026-07-25',
    },
    {
        id: 3,
        type: 'carousel',
        platformKeys: ['facebook'],
        status: 'Scheduled',
        title: 'Top 10 Products',
        content: 'Swipe through our best selling products.',
        image: 'https://picsum.photos/800/450?3',
        likes: 0, comments: 0, shares: 0, views: 0,
        author: 'Marketing', created_at: '2026-08-02',
    },
    {
        id: 4,
        type: 'reel',
        platformKeys: ['instagram', 'tiktok'],
        status: 'Published',
        title: 'Behind The Scenes',
        content: 'A quick look inside our studio.',
        thumbnail: 'https://picsum.photos/800/450?44',
        video: 'https://www.w3schools.com/html/movie.mp4',
        likes: 2200, comments: 163, shares: 93, views: 12000,
        author: 'Creative Team', created_at: '2026-07-23',
    },
    {
        id: 5,
        type: 'image',
        platformKeys: ['linkedin', 'x'],
        status: 'Published',
        title: 'Hiring Laravel Developers',
        content: 'Join our engineering team.',
        image: 'https://picsum.photos/800/450?5',
        likes: 520, comments: 31, shares: 15, views: 2700,
        author: 'HR Team', created_at: '2026-07-22',
    },
    {
        id: 6,
        type: 'video',
        platformKeys: ['youtube', 'facebook'],
        status: 'Published',
        title: 'Product Demo',
        content: 'Watch our latest product demo.',
        thumbnail: 'https://picsum.photos/800/450?66',
        video: 'https://www.w3schools.com/html/mov_bbb.mp4',
        likes: 5400, comments: 360, shares: 220, views: 28500,
        author: 'Media Team', created_at: '2026-07-20',
    },
    {
        id: 7,
        type: 'image',
        platformKeys: ['x', 'facebook', 'instagram'],
        status: 'Scheduled',
        title: 'Black Friday Teaser',
        content: 'Something big is coming this Black Friday. Stay tuned!',
        image: 'https://picsum.photos/800/450?7',
        likes: 0, comments: 0, shares: 0, views: 0,
        author: 'Marketing', created_at: '2026-08-05',
    },
    {
        id: 8,
        type: 'carousel',
        platformKeys: ['instagram', 'linkedin'],
        status: 'Published',
        title: 'Customer Success Story',
        content: 'How Acme Corp doubled their output with our platform.',
        image: 'https://picsum.photos/800/450?8',
        likes: 340, comments: 18, shares: 9, views: 1650,
        author: 'John Smith', created_at: '2026-07-18',
    },
    {
        id: 9,
        type: 'reel',
        platformKeys: ['tiktok', 'instagram', 'youtube'],
        status: 'Published',
        title: 'Office Culture Highlights',
        content: 'A day in the life at our HQ.',
        thumbnail: 'https://picsum.photos/800/450?9',
        video: 'https://www.w3schools.com/html/movie.mp4',
        likes: 3100, comments: 210, shares: 140, views: 19800,
        author: 'Creative Team', created_at: '2026-07-15',
    },
    {
        id: 10,
        type: 'image',
        platformKeys: ['facebook', 'linkedin'],
        status: 'Scheduled',
        title: 'Webinar Announcement',
        content: 'Join our free webinar on growth marketing next Tuesday.',
        image: 'https://picsum.photos/800/450?10',
        likes: 0, comments: 0, shares: 0, views: 0,
        author: 'Marketing', created_at: '2026-08-04',
    },
    {
        id: 11,
        type: 'video',
        platformKeys: ['youtube'],
        status: 'Failed',
        title: 'Feature Walkthrough',
        content: 'A full walkthrough of our new dashboard features.',
        thumbnail: 'https://picsum.photos/800/450?11',
        video: 'https://www.w3schools.com/html/mov_bbb.mp4',
        likes: 0, comments: 0, shares: 0, views: 0,
        author: 'Media Team', created_at: '2026-07-12',
    },
    {
        id: 12,
        type: 'image',
        platformKeys: ['instagram', 'x'],
        status: 'Published',
        title: 'Meet The Team',
        content: 'Say hello to the people building your favorite features.',
        image: 'https://picsum.photos/800/450?12',
        likes: 890, comments: 52, shares: 12, views: 3400,
        author: 'HR Team', created_at: '2026-07-10',
    },
];

export const mockPosts = rawPosts.map(post => {

    const engagement = {};
    const platformUrls = {};

    post.platformKeys.forEach(key => {
        engagement[key] = buildEngagement(post, key);
        platformUrls[key] = buildPlatformUrl(post, key);
    });

    return {
        ...post,
        platforms: platformsFor(post.platformKeys),
        engagement,
        platformUrls,
    };
});

export function findPostById(id) {
    return mockPosts.find(post => post.id === Number(id));
}
