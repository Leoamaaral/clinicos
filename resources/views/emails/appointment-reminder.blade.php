<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
<x-mail::message>
# Lembrete de Tratamento

Olá **{{ $client->name }}**,

Este é um lembrete do seu tratamento agendado na **{{ $settings->clinic_name }}**.

**Tratamento(s):** {{ $treatmentNames }}  
**Data:** {{ $appointment->scheduled_at->format('d/m/Y') }}  
**Horário:** {{ $appointment->scheduled_at->format('H:i') }}

@if($appointment->notes)
**Observações:** {{ $appointment->notes }}
@endif

@if($settings->clinic_phone)
Para dúvidas, entre em contato: {{ $settings->clinic_phone }}
@endif

Atenciosamente,<br>
{{ $settings->clinic_name }}
</x-mail::message>
</body>
</html>
