export type PostStatus =
    | 'brief'
    | 'draft'
    | 'approved'
    | 'publishing'
    | 'published'
    | 'failed';

export type RunStatus = 'queued' | 'running' | 'published' | 'failed' | 'aborted';

export type AccountStatus = 'disconnected' | 'connected' | 'expired';

export type BrandProfileData = {
    voice: string | null;
    audience: string | null;
    topics: string[];
    bannedTerms: string[];
    examples: string[];
};

export type AccountData = {
    id: number;
    handle: string | null;
    status: AccountStatus;
    lastVerifiedAt: string | null;
};

export type DraftData = {
    id: number;
    variant: string;
    body: string;
    rationale: string | null;
    character_count: number;
};

export type ThreadMessage = {
    id: number;
    role: 'user' | 'agent';
    kind: 'text' | 'drafts' | 'run' | 'edit' | 'error';
    body: string | null;
    payload: Record<string, unknown> | null;
    created_at: string;
};

export type SpanProposal = {
    before: string;
    selection: string;
    after: string;
    instruction: string;
    replacement: string;
    settled: boolean;
};

export type StepData = {
    id: number;
    index: number;
    phase: 'observe' | 'think' | 'gate' | 'act' | 'verify';
    action: { action: string; ref: number | null; why: string } | null;
    reasoning: string | null;
    gate_decision: 'allowed' | 'denied' | null;
    gate_reason: string | null;
    latency_ms: number | null;
    error: string | null;
    screenshot_path?: string | null;
};

export type RunData = {
    id: number;
    status: RunStatus;
    mode: 'autonomous' | 'step';
    step_count: number;
    failure_reason: string | null;
};

export type PostSummary = {
    id: number;
    brief: string;
    status: PostStatus;
    approved_body: string | null;
    published_url: string | null;
};

export type BoardPost = {
    id: number;
    brief: string;
    body: string | null;
    publishedUrl: string | null;
    updatedAt: string;
    progress: { steps: number; budget: number } | null;
};

export type Suggestion = {
    label: string;
    hint: string;
    prompt: string | null;
    href: string | null;
};

export type HomeStatus = {
    activeRuns: number;
    awaitingPublish: number;
    accountsConnected: number;
    accountsTotal: number;
    teamSlug: string;
};
