import { Head } from '@inertiajs/react';
import AppLogoFull from '@/components/app-logo-full';
import { XCircle } from 'lucide-react';

export default function PublicAnamnesisInvalid() {
    return (
        <>
            <Head title="Link inválido" />
            <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6">
                <div className="w-full max-w-md text-center">
                    <AppLogoFull className="mx-auto h-36 w-auto max-w-[220px]" />
                    <XCircle className="text-destructive mx-auto mt-6 size-12" />
                    <h1 className="mt-4 text-2xl font-semibold">Link indisponível</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Este link expirou, já foi utilizado ou não é válido.
                        Entre em contato com a clínica para solicitar um novo link.
                    </p>
                </div>
            </div>
        </>
    );
}

PublicAnamnesisInvalid.layout = null;
