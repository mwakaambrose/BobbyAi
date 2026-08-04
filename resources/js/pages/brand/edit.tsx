import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import BrandProfileController from '@/actions/App/Http/Controllers/Agent/BrandProfileController';
import ExampleList from '@/components/brand/example-list';
import PromptPreview from '@/components/brand/prompt-preview';
import TagField from '@/components/brand/tag-field';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BrandProfileData } from '@/types/agent';

type Props = {
    brand: BrandProfileData;
};

/* Limits are the server's, repeated here so the form stops you before a
   round trip does. See App\Http\Requests\Agent\UpdateBrandProfileRequest. */
const MAX_TOPICS = 20;
const MAX_BANNED_TERMS = 50;
const MAX_EXAMPLES = 10;

/* Values reach the server as arrays. Joining on a unit separator rather than a
   comma or a blank line means a topic may contain a comma and an example may
   contain a paragraph break without being split in two. */
const SEPARATOR = '\u001F';

const TEXTAREA =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm leading-relaxed shadow-xs transition-[color,box-shadow,border-color] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40';

export default function BrandEdit({ brand }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    const [voice, setVoice] = useState(brand.voice ?? '');
    const [audience, setAudience] = useState(brand.audience ?? '');
    const [topics, setTopics] = useState(brand.topics);
    const [bannedTerms, setBannedTerms] = useState(brand.bannedTerms);
    const [examples, setExamples] = useState(brand.examples);

    return (
        <>
            <Head title="Brand voice" />

            <PageContainer>
                <PageHeader
                    eyebrow="How the agent writes"
                    title="Brand voice"
                    description="Everything the agent writes is conditioned on this."
                />

                <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_19rem]">
                    <Form
                        {...BrandProfileController.update.form({
                            current_team: teamSlug,
                        })}
                        options={{ preserveScroll: true }}
                        transform={(data) => ({
                            ...data,
                            topics: split(data.topics),
                            banned_terms: split(data.banned_terms),
                            examples: split(data.examples),
                        })}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="topics"
                                    value={topics.join(SEPARATOR)}
                                />
                                <input
                                    type="hidden"
                                    name="banned_terms"
                                    value={bannedTerms.join(SEPARATOR)}
                                />
                                <input
                                    type="hidden"
                                    name="examples"
                                    value={examples
                                        .map((example) => example.trim())
                                        .filter(Boolean)
                                        .join(SEPARATOR)}
                                />

                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Voice and audience
                                        </CardTitle>
                                        <CardDescription>
                                            How the agent should sound, and who
                                            it&apos;s talking to.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-6">
                                        <div className="grid gap-2">
                                            <Label htmlFor="voice">Voice</Label>
                                            <textarea
                                                id="voice"
                                                name="voice"
                                                rows={3}
                                                maxLength={1000}
                                                value={voice}
                                                onChange={(event) =>
                                                    setVoice(event.target.value)
                                                }
                                                placeholder="Warm, direct, jargon-free."
                                                className={TEXTAREA}
                                            />
                                            <InputError
                                                message={errors.voice}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="audience">
                                                Audience
                                            </Label>
                                            <Input
                                                id="audience"
                                                name="audience"
                                                maxLength={1000}
                                                value={audience}
                                                onChange={(event) =>
                                                    setAudience(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Members of a savings cooperative."
                                            />
                                            <InputError
                                                message={errors.audience}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Content rules</CardTitle>
                                        <CardDescription>
                                            Guardrails the agent must follow on
                                            every generation.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-6">
                                        <TagField
                                            id="topics"
                                            label="Topics"
                                            values={topics}
                                            onChange={setTopics}
                                            max={MAX_TOPICS}
                                            placeholder="savings"
                                            hint="Press Enter after each one."
                                            error={errors.topics}
                                        />

                                        <TagField
                                            id="banned_terms"
                                            label="Never use"
                                            values={bannedTerms}
                                            onChange={setBannedTerms}
                                            max={MAX_BANNED_TERMS}
                                            tone="blocked"
                                            placeholder="synergy"
                                            hint="Checked in code after every generation, not left to the model."
                                            error={errors.banned_terms}
                                        />
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Examples</CardTitle>
                                        <CardDescription>
                                            Posts that sound like you, used as
                                            few-shot references.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ExampleList
                                            values={examples}
                                            onChange={setExamples}
                                            max={MAX_EXAMPLES}
                                        />
                                        <InputError
                                            className="mt-2"
                                            message={errors.examples}
                                        />
                                    </CardContent>
                                </Card>

                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-border pt-5">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save brand voice'}
                                    </Button>
                                    <p className="text-meta text-muted-foreground">
                                        Applies to the next draft the agent
                                        writes.
                                    </p>
                                </div>
                            </>
                        )}
                    </Form>

                    <PromptPreview
                        brand={{
                            voice,
                            audience,
                            topics,
                            bannedTerms,
                            examples,
                        }}
                    />
                </div>
            </PageContainer>
        </>
    );
}

/** Turn a joined hidden field back into the array the server validates. */
function split(value: unknown): string[] {
    return String(value ?? '')
        .split(SEPARATOR)
        .map((entry) => entry.trim())
        .filter(Boolean);
}
