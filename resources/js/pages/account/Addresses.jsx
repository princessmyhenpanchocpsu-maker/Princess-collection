import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import client from '../../api/client';
import AddressForm, { AddressLines } from '../../components/AddressForm';
import ConfirmationModal from '../../components/ConfirmationModal';
import EmptyState, { ErrorState } from '../../components/EmptyState';
import LoadingSpinner from '../../components/LoadingSpinner';
import Modal from '../../components/Modal';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import useApi from '../../hooks/useApi';
import { errorMessage } from '../../lib/errors';

export default function Addresses() {
    const { user } = useAuth();
    const toast = useToast();
    const { data, loading, error, reload } = useApi((signal) => client.get('/addresses', { signal }));
    const [editing, setEditing] = useState(null); // null | 'new' | address
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);

    const confirmDelete = async () => {
        setBusy(true);
        try {
            await client.delete(`/addresses/${deleting.id}`);
            toast.success('Address removed.');
            setDeleting(null);
            reload();
        } catch (failure) {
            toast.error(errorMessage(failure));
        } finally {
            setBusy(false);
        }
    };

    if (loading && !data) return <LoadingSpinner />;
    if (error) return <ErrorState error={error} onRetry={reload} />;

    const list = data.data;

    return (
        <div>
            <div className="mb-5 flex items-center justify-between gap-3">
                <h2 className="font-display text-xl font-semibold text-brown">Saved addresses</h2>
                <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}>
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Add address
                </button>
            </div>

            {list.length === 0 ? (
                <EmptyState icon={MapPin} title="No saved addresses" message="Add one now to check out faster next time." />
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2">
                    {list.map((address) => (
                        <li key={address.id} className="card flex flex-col p-5">
                            <AddressLines address={address} />
                            {address.is_default && <span className="mt-3 w-fit rounded-full bg-blush px-2.5 py-0.5 text-xs font-medium text-brown">Default</span>}
                            <div className="mt-4 flex gap-2 pt-1">
                                <button type="button" className="btn btn-secondary px-4 py-1.5" onClick={() => setEditing(address)}>
                                    <Pencil className="h-3.5 w-3.5" aria-hidden="true" /> Edit
                                </button>
                                <button type="button" className="btn btn-ghost py-1.5 hover:text-red-700" onClick={() => setDeleting(address)}>
                                    <Trash2 className="h-3.5 w-3.5" aria-hidden="true" /> Remove
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title={editing === 'new' ? 'New address' : 'Edit address'}>
                {editing && (
                    <AddressForm
                        address={editing === 'new' ? undefined : editing}
                        defaults={editing === 'new' ? { recipient_name: user.name, phone: user.phone ?? '' } : {}}
                        onCancel={() => setEditing(null)}
                        onSaved={() => {
                            setEditing(null);
                            toast.success('Address saved.');
                            reload();
                        }}
                    />
                )}
            </Modal>

            <ConfirmationModal
                open={Boolean(deleting)}
                title="Remove this address?"
                message="It will be deleted from your account. Orders you already placed keep their delivery details."
                confirmLabel="Remove address"
                loading={busy}
                onConfirm={confirmDelete}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
