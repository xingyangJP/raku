import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/Components/ui/card';
import axios from 'axios';

export default function InvoiceEdit({ auth, invoice }) {
  // Normalize possible ISO strings from backend (e.g., 2025-09-10T00:00:00Z)
  const norm = (v) => {
    if (!v) return '';
    if (typeof v === 'string' && v.includes('T')) return v.split('T')[0];
    return v;
  };

  const { data, setData, patch, processing, errors } = useForm({
    ...invoice,
    billing_date: norm(invoice?.billing_date),
    due_date: norm(invoice?.due_date),
  });
  const [departments, setDepartments] = useState([]);
  const [items, setItems] = useState(invoice.items || []);
  const isMfCreated = !!data.mf_billing_id;

  useEffect(() => {
    if (data.client_id) {
      axios
        .get(`/api/partners/${encodeURIComponent(data.client_id)}/departments`)
        .then((res) => {
          const arr = Array.isArray(res.data) ? res.data : [];
          setDepartments(arr);
        })
        .catch(() => setDepartments([]))
    } else {
      setDepartments([]);
    }
  }, [data.client_id]);

  useEffect(() => {
    const subtotal = items.reduce((sum, it) => sum + (Number(it.qty || 0) * Number(it.price || 0)), 0);
    const tax = Math.round(subtotal * 0.1);
    setData('items', items);
    setData('total_amount', subtotal + tax);
    setData('tax_amount', tax);
  }, [items]);

  const onSubmit = (e) => {
    e.preventDefault();
    if (isMfCreated) return;
    router.patch(route('invoices.update', { invoice: data.id }), data, { preserveScroll: true, preserveState: true });
  }

  const sendToMF = () => {
    // 送信前に現在の入力内容を自動保存してから送信
    router.patch(
      route('invoices.update', { invoice: data.id }),
      data,
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => router.visit(route('invoices.send.start', { invoice: data.id })),
      }
    );
  }

  const viewPdf = () => {
    router.visit(route('invoices.viewPdf.start', { invoice: data.id }));
  }

  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">請求書編集</h2>}>
      <Head title="請求書編集" />
      <div className="py-12">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
          <form onSubmit={onSubmit} className="space-y-6">
            <Card>
              <CardHeader><CardTitle>基本情報</CardTitle></CardHeader>
              <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label>顧客名</Label>
                  <Input value={data.customer_name || ''} onChange={(e)=>setData('customer_name', e.target.value)} disabled={isMfCreated} />
                </div>
                <div>
                  <Label>取引先部門</Label>
                  <select value={data.department_id || ''} onChange={(e)=>setData('department_id', e.target.value)} className="border rounded h-10 w-full px-2" disabled={isMfCreated}>
                    <option value="">選択してください</option>
                    {departments.map(d => (
                      <option key={d.id} value={d.id}>{d.name || d.person_dept || d.id}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <Label>件名</Label>
                  <Input value={data.title || ''} onChange={(e)=>setData('title', e.target.value)} disabled={isMfCreated} />
                </div>
                <div>
                  <Label>請求番号</Label>
                  <Input value={data.billing_number || ''} onChange={(e)=>setData('billing_number', e.target.value)} disabled={isMfCreated} />
                </div>
                <div>
                  <Label>請求日</Label>
                  <Input type="date" value={data.billing_date || ''} onChange={(e)=>setData('billing_date', e.target.value)} disabled={isMfCreated} />
                </div>
                <div>
                  <Label>支払期限</Label>
                  <Input type="date" value={data.due_date || ''} onChange={(e)=>setData('due_date', e.target.value)} disabled={isMfCreated} />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle>明細</CardTitle></CardHeader>
              <CardContent>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left border-b">
                        <th className="p-2">品目名</th>
                        <th className="p-2">数量</th>
                        <th className="p-2">単位</th>
                        <th className="p-2">単価</th>
                        <th className="p-2">金額</th>
                        <th className="p-2"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((it, idx) => (
                        <tr key={it.id || idx} className="border-b">
                          <td className="p-2"><Input value={it.name || ''} onChange={(e)=>setItems(prev=>prev.map((p,i)=>i===idx?{...p,name:e.target.value}:p))} disabled={isMfCreated} /></td>
                          <td className="p-2 w-24"><Input type="number" value={it.qty ?? 1} onChange={(e)=>setItems(prev=>prev.map((p,i)=>i===idx?{...p,qty:Number(e.target.value)}:p))} disabled={isMfCreated} /></td>
                          <td className="p-2 w-24"><Input value={it.unit || ''} onChange={(e)=>setItems(prev=>prev.map((p,i)=>i===idx?{...p,unit:e.target.value}:p))} disabled={isMfCreated} /></td>
                          <td className="p-2 w-32"><Input type="number" value={it.price ?? 0} onChange={(e)=>setItems(prev=>prev.map((p,i)=>i===idx?{...p,price:Number(e.target.value)}:p))} disabled={isMfCreated} /></td>
                          <td className="p-2 w-32 text-right">{Number(it.qty||0)*Number(it.price||0)}</td>
                          <td className="p-2 w-16"><Button type="button" variant="destructive" onClick={()=>setItems(prev=>prev.filter((_,i)=>i!==idx))}>削除</Button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="mt-3"><Button type="button" onClick={()=>setItems(prev=>[...prev,{id:Date.now(),name:'',qty:1,unit:'式',price:0}])} disabled={isMfCreated}>行を追加</Button></div>
                <div className="mt-4 text-right">小計+税: {data.total_amount || 0}（税額: {data.tax_amount || 0}）</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle>備考（対外）</CardTitle></CardHeader>
              <CardContent>
                <Textarea value={data.notes || ''} onChange={(e)=>setData('notes', e.target.value)} placeholder="お見積りの有効期限は発行後1ヶ月です。" disabled={isMfCreated} />
              </CardContent>
            </Card>
          </form>
          <div className="mt-6 flex justify-end gap-2">
            {data.mf_billing_id ? (
              <>
                <a href={`https://invoice.moneyforward.com/billings/${data.mf_billing_id}/edit`} target="_blank" rel="noopener noreferrer">
                    <Button type="button">MFで請求書を編集する</Button>
                </a>
                <Button type="button" onClick={viewPdf}>PDFを確認</Button>
              </>
            ) : (
              <Button type="button" onClick={sendToMF}>MFで請求書を作成する</Button>
            )}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}