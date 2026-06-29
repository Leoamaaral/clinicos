import type { FormComponentRef } from '@inertiajs/core';
import { useEffect, useRef, useState } from 'react';

export function useAnamnesisConsentSubmit() {
    const inertiaFormRef = useRef<FormComponentRef>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [termsAccepted, setTermsAccepted] = useState(false);
    const pendingSubmitRef = useRef(false);

    const openConsentModal = () => {
        setModalOpen(true);
    };

    const handleSubmitClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        if (termsAccepted) {
            return;
        }

        event.preventDefault();
        openConsentModal();
    };

    const handleBeforeSubmit = () => {
        if (termsAccepted) {
            return true;
        }

        openConsentModal();

        return false;
    };

    const handleConfirm = () => {
        setTermsAccepted(true);
        setModalOpen(false);
        pendingSubmitRef.current = true;
    };

    useEffect(() => {
        if (!pendingSubmitRef.current || !termsAccepted) {
            return;
        }

        pendingSubmitRef.current = false;
        inertiaFormRef.current?.submit();
    }, [termsAccepted]);

    return {
        inertiaFormRef,
        modalOpen,
        setModalOpen,
        termsAccepted,
        handleSubmitClick,
        handleBeforeSubmit,
        handleConfirm,
    };
}
