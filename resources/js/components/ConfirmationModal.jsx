import Button from './Button';
import Modal from './Modal';

/**
 * Asks before a destructive or hard-to-undo action. The confirm button names the action itself.
 */
export default function ConfirmationModal({
    open,
    title,
    message,
    confirmLabel,
    cancelLabel = 'Keep it',
    danger = true,
    loading = false,
    onConfirm,
    onCancel,
    children,
}) {
    return (
        <Modal
            open={open}
            onClose={loading ? () => {} : onCancel}
            title={title}
            size="max-w-md"
            footer={
                <>
                    <Button variant="secondary" onClick={onCancel} disabled={loading}>
                        {cancelLabel}
                    </Button>
                    <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <p className="text-sm text-brown">{message}</p>
            {children}
        </Modal>
    );
}
