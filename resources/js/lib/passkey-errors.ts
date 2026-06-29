const passkeyErrorMessages: Record<string, string> = {
    'The passkey operation was cancelled.':
        'A operação com a chave de acesso foi cancelada.',
    'Passkeys are not supported in this browser.':
        'Chaves de acesso não são suportadas neste navegador.',
    'This device is already registered as a passkey.':
        'Este dispositivo já está registrado como chave de acesso.',
};

export function translatePasskeyError(message: string): string {
    return passkeyErrorMessages[message] ?? message;
}
