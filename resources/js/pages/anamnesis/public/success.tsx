import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import AppLogoFull from '@/components/app-logo-full';

type Props = {
    clinicName: string;
};

export default function PublicAnamnesisSuccess({ clinicName }: Props) {
    return (
        <>
            <Head title={`Anamnese enviada - ${clinicName}`} />
            <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6">
                <div className="w-full max-w-md text-center">
                    <AppLogoFull className="mx-auto h-36 w-auto max-w-[220px]" />
                    <CheckCircle2 className="text-primary mx-auto mt-6 size-12" />
                    <h1 className="mt-4 text-2xl font-semibold">Anamnese enviada!</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Obrigado, {clinicName} recebeu suas informações com sucesso.
                        Você pode fechar esta página.
                    </p>
                </div>
            </div>
        </>
    );
}

PublicAnamnesisSuccess.layout = null;
