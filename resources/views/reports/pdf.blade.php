<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório — {{ $clinic_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.4;
            margin: 0;
            padding: 24px;
        }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 {
            font-size: 14px;
            margin: 24px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 20px; }
        .kpis {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .kpis td {
            width: 20%;
            padding: 10px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .kpi-label { color: #6b7280; font-size: 9px; text-transform: uppercase; }
        .kpi-value { font-size: 16px; font-weight: bold; margin-top: 4px; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data th,
        table.data td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
        }
        table.data th {
            background: #f9fafb;
            font-size: 10px;
            text-transform: uppercase;
            color: #4b5563;
        }
        .text-right { text-align: right; }
        .muted { color: #6b7280; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>{{ $clinic_name }}</h1>
    <p class="meta">
        Relatório gerado em {{ $generated_at }}<br>
        Período: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}
        a {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
    </p>

    <h2>Resumo financeiro</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Faturamento</div>
                <div class="kpi-value">R$ {{ number_format($summary['revenue'], 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="kpi-label">Compras pagas</div>
                <div class="kpi-value">{{ $summary['paid_purchase_count'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Cortesias</div>
                <div class="kpi-value">{{ $summary['courtesy_count'] }}</div>
                <div class="muted" style="margin-top: 4px;">
                    ref. R$ {{ number_format($summary['courtesy_reference_value'], 2, ',', '.') }}
                    • {{ $summary['courtesy_sessions'] }} sessões
                </div>
            </td>
            <td>
                <div class="kpi-label">Ticket médio</div>
                <div class="kpi-value">R$ {{ number_format($summary['average_ticket'], 2, ',', '.') }}</div>
                <div class="muted" style="margin-top: 4px;">compras pagas</div>
            </td>
            <td>
                <div class="kpi-label">vs período anterior</div>
                <div class="kpi-value">
                    @if ($summary['revenue_change_percent'] === null)
                        —
                    @else
                        {{ $summary['revenue_change_percent'] > 0 ? '+' : '' }}{{ number_format($summary['revenue_change_percent'], 1, ',', '.') }}%
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <h2>Recebimento líquido (maquinha)</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Receita líquida</div>
                <div class="kpi-value">R$ {{ number_format($payment_summary['net_revenue'], 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="kpi-label">Taxas da maquinha</div>
                <div class="kpi-value">R$ {{ number_format($payment_summary['total_fees'], 2, ',', '.') }}</div>
                <div class="muted" style="margin-top: 4px;">
                    {{ number_format($payment_summary['fee_percent_of_gross'], 2, ',', '.') }}% do bruto
                </div>
            </td>
            <td>
                <div class="kpi-label">Antecipação</div>
                <div class="kpi-value">{{ number_format($payment_summary['anticipation_rate'], 2, ',', '.') }}% a.m.</div>
                <div class="muted" style="margin-top: 4px;">parcelado vendedor</div>
            </td>
            <td colspan="2">
                <div class="kpi-label">Sem detalhe de pagamento</div>
                <div class="kpi-value">R$ {{ number_format($payment_summary['untracked_revenue'], 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Forma de pagamento</th>
                <th class="text-right">Taxa</th>
                <th class="text-right">Bruto</th>
                <th class="text-right">Taxas</th>
                <th class="text-right">Líquido</th>
                <th class="text-right">Qtd.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payment_summary['channels'] as $channel)
                <tr>
                    <td>{{ $channel['label'] }}</td>
                    <td class="text-right">{{ number_format($channel['fee_percent'], 2, ',', '.') }}%</td>
                    <td class="text-right">R$ {{ number_format($channel['gross'], 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($channel['fees'], 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($channel['net'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ $channel['transaction_count'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Nenhum pagamento registrado no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Faturamento por período</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Período</th>
                <th class="text-right">Faturamento</th>
                <th class="text-right">Compras pagas</th>
                <th class="text-right">Cortesias</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($revenue_chart['labels'] as $index => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="text-right">R$ {{ number_format($revenue_chart['revenue'][$index], 2, ',', '.') }}</td>
                    <td class="text-right">{{ $revenue_chart['purchases'][$index] }}</td>
                    <td class="text-right">{{ $revenue_chart['courtesies'][$index] ?? 0 }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Nenhuma venda no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Tratamentos mais vendidos</h2>
    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Tratamento</th>
                <th class="text-right">Receita</th>
                <th class="text-right">Sessões</th>
                <th class="text-right">Vendas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($top_treatments as $index => $treatment)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $treatment['treatment_name'] }}</td>
                    <td class="text-right">R$ {{ number_format($treatment['revenue'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ $treatment['sessions_sold'] }}</td>
                    <td class="text-right">{{ $treatment['sales_count'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Nenhum tratamento vendido no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>Clientes que mais compram</h2>
    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th class="text-right">Total gasto</th>
                <th class="text-right">Compras</th>
                <th class="text-right">Ticket médio</th>
                <th>Última compra</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($top_clients as $index => $client)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $client['client_name'] }}</td>
                    <td class="text-right">R$ {{ number_format($client['total_spent'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ $client['purchase_count'] }}</td>
                    <td class="text-right">R$ {{ number_format($client['average_ticket'], 2, ',', '.') }}</td>
                    <td>{{ \Carbon\Carbon::parse($client['last_purchase'])->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Nenhum cliente com compras no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>Mix de vendas</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Tipo</th>
                <th class="text-right">Vendas</th>
                <th class="text-right">%</th>
                <th class="text-right">Receita</th>
                <th class="text-right">%</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales_mix['items'] as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td class="text-right">{{ $item['count'] }}</td>
                    <td class="text-right">{{ number_format($item['count_percent'], 1, ',', '.') }}%</td>
                    <td class="text-right">R$ {{ number_format($item['revenue'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item['revenue_percent'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Nenhuma venda no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Status da agenda</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Total</div>
                <div class="kpi-value">{{ $appointment_status['summary']['total'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Taxa de conclusão</div>
                <div class="kpi-value">{{ number_format($appointment_status['summary']['completion_rate'], 1, ',', '.') }}%</div>
            </td>
            <td>
                <div class="kpi-label">Taxa de cancelamento</div>
                <div class="kpi-value">{{ number_format($appointment_status['summary']['cancellation_rate'], 1, ',', '.') }}%</div>
            </td>
            <td>
                <div class="kpi-label">Concluídos / Cancelados</div>
                <div class="kpi-value">{{ $appointment_status['summary']['completed'] }} / {{ $appointment_status['summary']['cancelled'] }}</div>
            </td>
        </tr>
    </table>

    <h2>Produtividade por profissional</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Profissional</th>
                <th class="text-right">Total</th>
                <th class="text-right">Concluídos</th>
                <th class="text-right">Horas</th>
                <th class="text-right">Clientes</th>
                <th class="text-right">Cancel.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($professional_productivity as $row)
                <tr>
                    <td>{{ $row['professional_name'] }}</td>
                    <td class="text-right">{{ $row['total_appointments'] }}</td>
                    <td class="text-right">{{ $row['completed'] }}</td>
                    <td class="text-right">{{ number_format($row['hours'], 1, ',', '.') }}h</td>
                    <td class="text-right">{{ $row['unique_clients'] }}</td>
                    <td class="text-right">{{ number_format($row['cancellation_rate'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Nenhum agendamento no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Clientes inativos com sessões <span class="muted">(últimos {{ $inactive_clients['summary']['inactive_days'] }} dias)</span></h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Clientes</div>
                <div class="kpi-value">{{ $inactive_clients['summary']['client_count'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Sessões pendentes</div>
                <div class="kpi-value">{{ $inactive_clients['summary']['sessions_remaining'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Valor estimado</div>
                <div class="kpi-value">R$ {{ number_format($inactive_clients['summary']['estimated_value'], 2, ',', '.') }}</div>
            </td>
            <td></td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Cliente</th>
                <th class="text-right">Sessões</th>
                <th class="text-right">Valor est.</th>
                <th>Última visita</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inactive_clients['items'] as $client)
                <tr>
                    <td>{{ $client['client_name'] }}</td>
                    <td class="text-right">{{ $client['sessions_remaining'] }}</td>
                    <td class="text-right">R$ {{ number_format($client['estimated_value'], 2, ',', '.') }}</td>
                    <td>
                        @if ($client['last_completed_at'])
                            {{ \Carbon\Carbon::parse($client['last_completed_at'])->format('d/m/Y') }}
                            ({{ $client['days_since_last_visit'] }}d)
                        @else
                            Nunca
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Nenhum cliente inativo com sessões.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>Horários de pico</h2>
    @if ($peak_hours['total'] === 0)
        <p class="muted">Nenhum agendamento no período.</p>
    @else
        @if ($peak_hours['busiest'])
            <p class="muted">
                Horário de pico: <strong>{{ $peak_hours['busiest']['day'] }} às {{ $peak_hours['busiest']['hour'] }}</strong>
                ({{ $peak_hours['busiest']['count'] }} agendamentos)
            </p>
        @endif
        <table class="data">
            <thead>
                <tr>
                    <th>Dia / Hora</th>
                    @foreach ($peak_hours['hours'] as $hour)
                        <th class="text-right">{{ $hour }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($peak_hours['days'] as $dayIndex => $day)
                    <tr>
                        <td>{{ $day }}</td>
                        @foreach ($peak_hours['matrix'][$dayIndex] as $count)
                            <td class="text-right">{{ $count > 0 ? $count : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Anamnese</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Convites enviados</div>
                <div class="kpi-value">{{ $anamnesis['summary']['invitations_sent'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Taxa de preenchimento</div>
                <div class="kpi-value">{{ number_format($anamnesis['summary']['fill_rate'], 1, ',', '.') }}%</div>
            </td>
            <td>
                <div class="kpi-label">Fichas criadas</div>
                <div class="kpi-value">{{ $anamnesis['summary']['records_created'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Sem anamnese</div>
                <div class="kpi-value">{{ $anamnesis['summary']['clients_without_anamnesis'] }}</div>
            </td>
        </tr>
    </table>
    @foreach ($anamnesis['question_stats'] as $question)
        <p><strong>{{ $question['question'] }}</strong> ({{ $question['total_responses'] }} respostas)</p>
        <table class="data">
            <thead>
                <tr>
                    <th>Resposta</th>
                    <th class="text-right">Qtd</th>
                    <th class="text-right">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($question['responses'] as $response)
                    <tr>
                        <td>{{ $response['label'] }}</td>
                        <td class="text-right">{{ $response['count'] }}</td>
                        <td class="text-right">{{ number_format($response['percent'], 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>Comunicações</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Total</div>
                <div class="kpi-value">{{ $notifications['summary']['total'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Taxa de entrega</div>
                <div class="kpi-value">{{ number_format($notifications['summary']['delivery_rate'], 1, ',', '.') }}%</div>
            </td>
            <td>
                <div class="kpi-label">Enviadas</div>
                <div class="kpi-value">{{ $notifications['summary']['sent'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Falhas</div>
                <div class="kpi-value">{{ $notifications['summary']['failed'] }}</div>
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Canal</th>
                <th class="text-right">Total</th>
                <th class="text-right">Enviadas</th>
                <th class="text-right">Falhas</th>
                <th class="text-right">Entrega</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notifications['by_channel'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right">{{ $row['total'] }}</td>
                    <td class="text-right">{{ $row['sent'] }}</td>
                    <td class="text-right">{{ $row['failed'] }}</td>
                    <td class="text-right">{{ number_format($row['delivery_rate'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Nenhuma notificação no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if (count($notifications['recent_failures']) > 0)
        <p><strong>Falhas recentes</strong></p>
        <table class="data">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Canal</th>
                    <th>Tipo</th>
                    <th>Erro</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notifications['recent_failures'] as $failure)
                    <tr>
                        <td>{{ $failure['client_name'] }}</td>
                        <td>{{ $failure['channel'] }}</td>
                        <td>{{ $failure['type'] }}</td>
                        <td>{{ $failure['error_message'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Sessões não utilizadas <span class="muted">(situação atual)</span></h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Sessões restantes</div>
                <div class="kpi-value">{{ $unused_sessions['summary']['total_sessions'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Valor estimado</div>
                <div class="kpi-value">R$ {{ number_format($unused_sessions['summary']['estimated_value'], 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="kpi-label">Clientes</div>
                <div class="kpi-value">{{ $unused_sessions['summary']['client_count'] }}</div>
            </td>
            <td></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Tratamento</th>
                <th class="text-right">Sessões</th>
                <th class="text-right">Valor est.</th>
                <th>Compra em</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($unused_sessions['items'] as $item)
                <tr>
                    <td>{{ $item['client_name'] }}</td>
                    <td>{{ $item['treatment_name'] }}</td>
                    <td class="text-right">{{ $item['sessions_remaining'] }}</td>
                    <td class="text-right">R$ {{ number_format($item['estimated_value'], 2, ',', '.') }}</td>
                    <td>{{ \Carbon\Carbon::parse($item['purchased_at'])->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Nenhuma sessão pendente de utilização.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
