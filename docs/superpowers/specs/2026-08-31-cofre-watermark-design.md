# Cofre: marca d'água real nos PDFs baixados — Issue #44

## Contexto

O banner do Cofre (`resources/views/livewire/membros/cofre.blade.php:16`) e o toast do
protótipo (`resources/views/prototype/cofre.blade.php:17`) prometem que cada documento sai
marcado com o nome do usuário que o baixou. `VaultDocumentOpenController` hoje só faz
`Storage::download()` do arquivo bruto ou redireciona pra `file_url` — não existe nenhuma
lógica de marca d'água. A copy promete uma proteção que não existe.

`VaultDocument` (`app/Models/VaultDocument.php`) aceita tanto arquivo enviado (`file_path`, em
`Storage::disk('public')`) quanto link externo (`file_url`), de tipos variados: PDF, vídeo
(mp4/mov/webm), planilha (xlsx/xls), documento (doc/docx) — ver `iconLabel()`. Dos dados
seedados (`DemoDataSeeder::seedVaultDocuments`), um documento é um link externo pra xlsx e o
outro é um PDF enviado.

## Escopo

Marca d'água real só nos **PDFs enviados** (`hasUploadedFile()` + extensão `pdf`) — único tipo
tecnicamente viável sem dependências pesadas (converter docx/xlsx/vídeo pra PDF exigiria algo
como LibreOffice/unoconv, complexidade desproporcional pra uma issue de prioridade baixa).
Links externos e arquivos não-PDF continuam servidos exatamente como hoje, sem tentativa de
carimbo. A copy do banner do Cofre é ajustada pra refletir esse escopo real.

Fora de escopo: qualquer alteração no protótipo (`resources/views/prototype/cofre.blade.php`)
— ele é a referência visual/copy, não algo a "corrigir".

## Decisões

- **Biblioteca:** `setasign/fpdi` (v2.6.8) + `setasign/fpdf` (v1.9.0), ambas MIT — verificado no
  Packagist antes de escolher, por causa da regra do projeto contra dependências AGPL. FPDI
  importa cada página do PDF original como template; FPDF desenha o texto do carimbo por cima.
- **Quando gerar:** na hora do download (`VaultDocumentOpenController`), não pré-gerado nem
  cacheado em disco. Evita duplicar storage e evita carimbo desatualizado se o nome/e-mail do
  membro mudar depois do upload do documento.
- **Conteúdo do carimbo:** nome + e-mail do membro + data do download — ex.
  `"Ricardo Mendes · ricardo@empresa.com · baixado em 31/08/2026"`.
- **Estilo visual:** carimbo discreto no rodapé de cada página (texto pequeno, cinza), não uma
  marca d'água diagonal cobrindo a página — prioriza legibilidade do conteúdo original.
- **Resiliência:** se a FPDI não conseguir interpretar o PDF de origem (arquivo corrompido, ou
  criptografado — a versão livre da FPDI não lida com todo tipo de criptografia), o controller
  cai de volta pra servir o arquivo original sem carimbo, em vez de quebrar o download. Isso
  também mantém os PDFs "falsos" do seeder de demonstração (bytes que começam com `%PDF-1.4`
  mas não são um PDF válido) funcionando sem erro.

## Mudanças

### Nova dependência

```bash
composer require setasign/fpdi setasign/fpdf
```

### `App\Services\PdfWatermarker` (novo)

Serviço sem estado. Método público recebe os bytes do PDF original + o texto do carimbo,
devolve os bytes do PDF carimbado:

```php
class PdfWatermarker
{
    public function stamp(string $pdfContents, string $stampText): string
    {
        // FPDI importa cada página do PDF original como template (via um stream
        // em memória, php://temp — FPDI::setSourceFile() aceita um resource além
        // de um caminho de arquivo, então não precisa tocar o disco), FPDF desenha
        // $stampText no rodapé de cada página, retorna o resultado de Output('S')
        // (string).
    }
}
```

Lança uma exceção normal (não trata erro internamente) se a FPDI não conseguir interpretar o
PDF — quem decide o fallback é o controller, não o serviço.

### `VaultDocumentOpenController`

Quando `$document->hasUploadedFile()` e a extensão do `file_path` é `pdf`:

1. Lê os bytes do arquivo original do disco.
2. Monta o texto do carimbo com nome/e-mail do usuário autenticado + data atual.
3. Tenta `PdfWatermarker::stamp()`. Em caso de exceção, loga um `warning` e segue com os bytes
   originais (sem carimbo).
4. Devolve uma `Response` com os bytes (carimbados ou originais), `Content-Type:
   application/pdf` e o mesmo `Content-Disposition: attachment` com o nome de arquivo que o
   `Storage::download()` já produzia hoje (mantém o comportamento observável pelo usuário e os
   testes de nome de arquivo existentes).

Para todo o resto (não-PDF enviado, ou `file_url`), o comportamento não muda.

### Copy (`resources/views/livewire/membros/cofre.blade.php`)

De:

> Documentos com seu nome gravado em cada página. Este espaço é individual e intransferível.

Para:

> PDFs baixados aqui trazem seu nome e e-mail carimbados em cada página. Este espaço é
> individual e intransferível.

## Testes

- **Unitário (`PdfWatermarker`):** gera um PDF mínimo válido com FPDF dentro do próprio teste
  (sem depender de fixture externa), chama `stamp()`, confere que o resultado começa com
  `%PDF`, é diferente dos bytes originais, e não lança exceção.
- **Feature (`VaultDocumentOpenTest`):**
  - Baixar um PDF real (gerado via FPDF no teste, guardado no disco fake) devolve bytes
    diferentes do arquivo original armazenado — confirma que o carimbo foi aplicado.
  - O teste existente que usa `UploadedFile::fake()->create('insights.pdf', ...)` (bytes que não
    formam um PDF válido) continua passando sem alteração — cobre o caminho de fallback.
  - Documento não-PDF enviado (ex. `.docx`) e documento com `file_url`: comportamento inalterado,
    sem tentativa de carimbo — testes existentes continuam valendo sem mudança.
- **Copy:** novo teste de feature confirmando que `/membros/cofre` exibe o texto ajustado do
  banner.

Suíte completa (`php artisan test`) deve continuar verde.
