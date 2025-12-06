import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Brain, Sparkles, ListChecks, ClipboardList, FileText, Printer } from 'lucide-react';
import axios from 'axios';

const defaultQuestions = [
    {
        title: 'ゴールの具体化',
        body: '今回の訪問で何を決めたいか？成功とみなせる状態や期限は？',
        keywords: ['ゴール', '目的', '成功', '期限'],
    },
    {
        title: '不明点の洗い出し',
        body: 'ゴール達成のために、まだ分からないこと・確認したいことは何か？',
        keywords: ['不明', '確認', '質問'],
    },
    {
        title: '関係者・影響範囲',
        body: '誰が関わり、誰に影響があるか？意思決定者・利用者・周辺部署は？',
        keywords: ['関係者', '影響', '意思決定'],
    },
    {
        title: '現状と課題',
        body: '今のやり方や課題は何か？どこを変えたいか？',
        keywords: ['現状', '課題', '困りごと'],
    },
    {
        title: '制約・前提',
        body: '予算・期限・利用できるリソースや既存ルールなどの制約は？',
        keywords: ['制約', '前提', '予算', '期限'],
    },
    {
        title: '次アクションと担当',
        body: '今日決めることと、持ち帰り事項の担当者・期限は？',
        keywords: ['次回', 'アクション', '期限', '担当'],
    },
];

const mustDecideToday = [
    '今日の訪問ゴール（何を決め切るか）',
    '未決事項が残った場合の持ち帰り先と期限',
    '次回アクションと担当（顧客側/自社側）',
];

export default function SalesAiCoachIndex() {
    const { auth } = usePage().props;
    const currentUserName = auth?.user?.name;
    const canManageSettings = ['守部', '川口'].includes(currentUserName);
    const [goal, setGoal] = useState('');
    const [context, setContext] = useState('');
    const [questionDraft, setQuestionDraft] = useState('');
    const [rewrite, setRewrite] = useState('');
    const [questions, setQuestions] = useState([]);
    const [doItems, setDoItems] = useState('');
    const [dontItems, setDontItems] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [goalError, setGoalError] = useState('');
    const [serverMessage, setServerMessage] = useState('');

    const handleRewrite = () => {
        if (!questionDraft) {
            setRewrite('質問を入力すると、深掘り版をここに表示します。');
            return;
        }
        setRewrite(`現状→理想→制約→例外→優先順位の順で聞く:\n- 現状: ${questionDraft}\n- 理想: 何をどこまで実現したいか？\n- 制約: 予算/期日/使えるシステムは？\n- 例外: 例外ルートや特別対応は？\n- 優先: どれが最優先か？`);
    };

    const computeQuestions = (text) => {
        const haystack = text.toLowerCase();
        const scored = defaultQuestions.map((q, idx) => {
            const hits = q.keywords?.reduce((acc, kw) => acc + (haystack.includes(kw) ? 1 : 0), 0) || 0;
            return { ...q, score: hits, order: idx };
        });
        const sorted = scored.sort((a, b) => {
            if (b.score === a.score) return a.order - b.order;
            return b.score - a.score;
        });
        const filtered = sorted.filter((q) => q.score > 0);
        const base = filtered.length >= 5 ? filtered : sorted;
        return base.map(({ score, order, ...rest }) => rest);
    };

    const handleGenerate = async () => {
        if (!goal.trim()) {
            setGoalError('「今日決めたいこと」を入力してください。');
            setQuestions([]);
            return;
        }
        setGoalError('');
        setServerMessage('');
        setIsGenerating(true);
        try {
            const response = await axios.post(route('sales-ai-coach.generate'), {
                goal,
                context,
            });
            const data = response.data || {};
            const incoming = Array.isArray(data.questions) ? data.questions : [];
            if (incoming.length > 0) {
                setQuestions(incoming);
            } else {
                setQuestions(computeQuestions(`${goal} ${context}`));
            }
            const incomingDo = Array.isArray(data.do) ? data.do : [];
            const incomingDont = Array.isArray(data.dont) ? data.dont : [];
            if (incomingDo.length > 0) {
                setDoItems(incomingDo.join('\n'));
            }
            if (incomingDont.length > 0) {
                setDontItems(incomingDont.join('\n'));
            }
            if (data.message) {
                setServerMessage(data.message);
            }
        } catch (error) {
            setServerMessage('AI生成に失敗したためテンプレを表示します。');
            setQuestions(computeQuestions(`${goal} ${context}`));
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-2xl font-semibold text-slate-800">訪問前AIコーチ</h2>
                    {canManageSettings && (
                        <Button asChild variant="ghost" size="sm">
                            <Link href={route('sales-ai-coach.settings')}>設定</Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="訪問前AIコーチ" />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-slate-800">
                                    <Brain className="h-5 w-5 text-indigo-600" />
                                    今日のゴールを決める
                                </CardTitle>
                                <CardDescription>訪問の目的を言語化すると、質問リストがぶれなくなります。</CardDescription>
                            </div>
                            <Badge variant="secondary">Step 1</Badge>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Label htmlFor="goal">今日決めたいこと / 聞きたいこと</Label>
                            <Textarea
                                id="goal"
                                placeholder="例: 受注〜出荷のボトルネックを洗い出したい／在庫の過不足アラート条件を決めたい／工程リードタイム短縮の打ち手を固めたい。"
                                value={goal}
                                onChange={(e) => setGoal(e.target.value)}
                            />
                            <Label htmlFor="context">過去議事録URL / 要約（任意）</Label>
                            <Textarea
                                id="context"
                                placeholder="例: 前回は現行フローと主要KPIを共有。未決: 在庫精度の目標値、工程の制約（設備/人員）、販売管理の帳票要件。"
                                value={context}
                                onChange={(e) => setContext(e.target.value)}
                            />
                            <div className="flex justify-end pt-2">
                                <Button onClick={handleGenerate} disabled={isGenerating}>
                                    {isGenerating ? '生成中...' : '優先質問を生成'}
                                </Button>
                            </div>
                            {goalError && <p className="text-sm text-red-500">{goalError}</p>}
                            {serverMessage && <p className="text-sm text-amber-600">{serverMessage}</p>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-slate-800">
                                    <ListChecks className="h-5 w-5 text-emerald-600" />
                                    優先質問（AI提案）
                                </CardTitle>
                                <CardDescription>致命的な抜けを防ぐためのテンプレです。不要なものは外し、追記してください。</CardDescription>
                            </div>
                            <Badge className="bg-emerald-50 text-emerald-700 border border-emerald-200">必須確認</Badge>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {questions.length === 0 && (
                                <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 p-4 text-sm text-slate-600">
                                    「今日決めたいこと」を入力して「優先質問を生成」を押すと表示されます。
                                </div>
                            )}
                            {questions.length > 0 && (
                                <div className="flex items-center gap-2 text-xs text-slate-600">
                                    <Badge variant="outline" className="border-slate-300 bg-white text-slate-700">今回のゴール</Badge>
                                    <span className="line-clamp-2">{goal}</span>
                                </div>
                            )}
                            {questions.map((q, idx) => (
                                <div key={idx} className="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                    <div className="flex items-start gap-3">
                                        <Checkbox id={`q-${idx}`} defaultChecked={idx < 5} className="mt-1" />
                                        <div className="space-y-1">
                                            <Label htmlFor={`q-${idx}`} className="text-sm font-semibold text-slate-800">{q.title}</Label>
                                            <p className="text-sm text-slate-600">{q.body}</p>
                                        </div>
                                    </div>
                                    <Input placeholder="追記メモ（任意）" />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-slate-800">
                                    <Sparkles className="h-5 w-5 text-amber-500" />
                                    質問リライト（深掘りアシスト）
                                </CardTitle>
                                <CardDescription>Yes/Noで終わらない聞き方に変換します。</CardDescription>
                            </div>
                            <Badge variant="secondary">Step 2</Badge>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Label htmlFor="draft">あなたの質問案</Label>
                            <Textarea
                                id="draft"
                                placeholder="例: 承認フローありますか？"
                                value={questionDraft}
                                onChange={(e) => setQuestionDraft(e.target.value)}
                            />
                            <div className="flex justify-end">
                                <Button variant="secondary" onClick={handleRewrite}>リライトする</Button>
                            </div>
                            <Label>深掘り版（プレビュー）</Label>
                            <Textarea
                                readOnly
                                value={rewrite || 'ここにリライト結果が表示されます。'}
                                className="bg-slate-50"
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-slate-800">
                                <ClipboardList className="h-5 w-5 text-sky-600" />
                                今日決め切る3点
                            </CardTitle>
                            <CardDescription>決まらない場合は誰がいつまでに持ち帰るかを明示します。</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {mustDecideToday.map((item, idx) => (
                                <div key={idx} className="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                                    <Checkbox id={`today-${idx}`} defaultChecked />
                                    <Label htmlFor={`today-${idx}`} className="text-sm text-slate-700">{item}</Label>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-slate-800">
                                <FileText className="h-5 w-5 text-purple-600" />
                                1ページ要約（ドラフト）
                            </CardTitle>
                            <CardDescription>ビジネス要求 / やる・やらない / 未決事項 / 次アクションをまとめます。</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-xl border border-purple-100 bg-purple-50/60 p-4 text-sm text-slate-700 space-y-2">
                                <p className="font-semibold text-slate-800">ビジネス要求</p>
                                <p>- {goal || '（ゴール未入力）'}</p>
                                <p className="font-semibold text-slate-800 pt-2">やる / やらない</p>
                                <Label className="text-xs text-slate-600">やる</Label>
                                <Textarea
                                    placeholder="例: 在庫アラート条件を決める / 品目同期を毎朝自動実行にする"
                                    value={doItems}
                                    onChange={(e) => setDoItems(e.target.value)}
                                    className="bg-white"
                                />
                                <Label className="text-xs text-slate-600">やらない</Label>
                                <Textarea
                                    placeholder="例: 旧システムからの帳票カスタムは行わない"
                                    value={dontItems}
                                    onChange={(e) => setDontItems(e.target.value)}
                                    className="bg-white"
                                />
                                <p className="font-semibold text-slate-800 pt-2">未決事項</p>
                                <p>- （未決事項を記入してください）</p>
                                <p className="font-semibold text-slate-800 pt-2">次アクション</p>
                                <p>- 顧客: （担当/期限を記入）</p>
                                <p>- 自社: （担当/期限を記入）</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-slate-800">
                                    <Printer className="h-5 w-5 text-slate-700" />
                                    印刷用アジェンダ
                                </CardTitle>
                                <CardDescription>ゴール・質問・決め切る項目を1枚にまとめて印刷できます。</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" onClick={() => window.print()}>
                                印刷
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-800 space-y-3">
                                <div>
                                    <p className="text-xs text-slate-500">ゴール</p>
                                    <p className="font-semibold">{goal || '（未入力）'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">議事録/補足</p>
                                    <p className="whitespace-pre-wrap">{context || '（未入力）'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500 mb-1">優先質問</p>
                                    <ol className="list-decimal pl-5 space-y-1">
                                        {(questions.length > 0 ? questions : defaultQuestions).map((q, idx) => (
                                            <li key={idx}>
                                                <span className="font-semibold">{q.title}：</span>
                                                <span className="ml-1">{q.body}</span>
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs text-slate-500">やる</p>
                                    <ul className="list-disc pl-5 space-y-1 whitespace-pre-wrap">
                                        {(doItems ? doItems.split('\n') : ['（訪問後に更新してください）']).map((item, idx) => (
                                            <li key={idx}>{item || '（空行）'}</li>
                                        ))}
                                    </ul>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs text-slate-500">やらない</p>
                                    <ul className="list-disc pl-5 space-y-1 whitespace-pre-wrap">
                                        {(dontItems ? dontItems.split('\n') : ['（訪問後に更新してください）']).map((item, idx) => (
                                            <li key={idx}>{item || '（空行）'}</li>
                                        ))}
                                    </ul>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500 mb-1">今日決め切ること</p>
                                    <ul className="list-disc pl-5 space-y-1">
                                        {mustDecideToday.map((item, idx) => (
                                            <li key={idx}>{item}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
