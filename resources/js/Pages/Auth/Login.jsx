import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

export default function Login({ status, canResetPassword }) {
    const [users, setUsers] = useState([]);

    const { data, setData, post, processing, errors, reset } = useForm({
        external_user_id: '',
        external_email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        let cancelled = false;
        const fetchUsers = async () => {
            try {
                const res = await axios.get(`/api/users`);
                const list = Array.isArray(res.data) ? res.data : [];
                if (!cancelled) {
                    // 名前昇順に並べ替え（任意）
                    list.sort((a,b) => String(a.name||'').localeCompare(String(b.name||''), 'ja'));
                    setUsers(list);
                    if (!data.external_user_id && list.length > 0) {
                        setData('external_user_id', String(list[0].id));
                        setData('external_email', list[0].email || '');
                    }
                }
            } catch (e) {
                console.error('ユーザー取得に失敗しました:', e);
                if (!cancelled) setUsers([]);
            }
        };
        fetchUsers();
        return () => { cancelled = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const sel = users.find(u => String(u.id) === String(data.external_user_id));
        if (sel) {
            setData('external_email', sel.email || '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.external_user_id, users]);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="ログイン" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="external_user_id" value="ユーザー" />
                    <select
                        id="external_user_id"
                        name="external_user_id"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.external_user_id}
                        onChange={(e) => setData('external_user_id', e.target.value)}
                    >
                        {users.map((u) => (
                            <option key={u.id} value={String(u.id)}>
                                {u.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.external_user_id} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="パスワード" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span className="ms-2 text-sm text-gray-600">ログイン状態を保持する</span>
                    </label>
                </div>

                <div className="mt-4 flex items-center justify-end">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            パスワードをお忘れですか？
                        </Link>
                    )}

                    <PrimaryButton className="ms-4" disabled={processing}>
                        ログイン
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
