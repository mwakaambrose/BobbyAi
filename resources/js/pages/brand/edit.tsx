import { Form, Head, usePage } from '@inertiajs/react';
import BrandProfileController from '@/actions/App/Http/Controllers/Agent/BrandProfileController';
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

const FIELD =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring/50';

export default function BrandEdit({ brand }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Brand voice" />

            <PageContainer width="narrow">
                <PageHeader
                    eyebrow="How the agent writes"
                    title="Brand voice"
                    description="Everything the agent writes is conditioned on this."
                />

                <Form
                    {...BrandProfileController.update.form({
                        current_team: teamSlug,
                    })}
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        topics: String(data.topics ?? '')
                            .split(',')
                            .map((value) => value.trim())
                            .filter(Boolean),
                        banned_terms: String(data.banned_terms ?? '')
                            .split(',')
                            .map((value) => value.trim())
                            .filter(Boolean),
                        examples: String(data.examples ?? '')
                            .split('\n\n')
                            .map((value) => value.trim())
                            .filter(Boolean),
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Voice and audience</CardTitle>
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
                                            defaultValue={brand.voice ?? ''}
                                            placeholder="Warm, direct, jargon-free."
                                            className={FIELD}
                                        />
                                        <InputError message={errors.voice} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="audience">
                                            Audience
                                        </Label>
                                        <Input
                                            id="audience"
                                            name="audience"
                                            defaultValue={brand.audience ?? ''}
                                            placeholder="Members of a savings cooperative."
                                        />
                                        <InputError message={errors.audience} />
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="topics">Topics</Label>
                                        <Input
                                            id="topics"
                                            name="topics"
                                            defaultValue={brand.topics.join(
                                                ', ',
                                            )}
                                            placeholder="savings, financial literacy"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Separate with commas.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="banned_terms">
                                            Never use
                                        </Label>
                                        <Input
                                            id="banned_terms"
                                            name="banned_terms"
                                            defaultValue={brand.bannedTerms.join(
                                                ', ',
                                            )}
                                            placeholder="synergy, leverage"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Checked in code after every
                                            generation, not left to the model.
                                        </p>
                                    </div>
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="examples">
                                            Examples
                                        </Label>
                                        <textarea
                                            id="examples"
                                            name="examples"
                                            rows={8}
                                            defaultValue={brand.examples.join(
                                                '\n\n',
                                            )}
                                            placeholder="Paste a post you were happy with. Separate each one with a blank line."
                                            className={FIELD}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Button type="submit" disabled={processing}>
                                Save brand voice
                            </Button>
                        </>
                    )}
                </Form>
            </PageContainer>
        </>
    );
}
