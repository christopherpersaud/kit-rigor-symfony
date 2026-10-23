# Docs de módulo

Cada `modulo-*.md` desta pasta é a especificação de um módulo, não documentação escrita depois.
As regras de negócio ficam na seção `## Regras`, uma por título, com um ID permanente e cenários
com valores concretos. A convenção completa está no `AGENTS.md`, bloco do kit, seção "Specs".

## Prefixos

Um prefixo de três letras por doc. Um módulo novo ganha um prefixo aqui na mesma mudança que cria
o doc.

| Prefixo | Módulo | Doc |
|---|---|---|
| `CLI` | Cliente (exemplo) | [modulo-cliente.md](modulo-cliente.md) |

## Formato de uma regra

```markdown
## Regras

### CLI-04 O documento é único por Conta

A mesma Conta não cadastra o mesmo CPF/CNPJ duas vezes; outra Conta pode.

- **Dado** um cliente com o CPF X na Conta, **quando** cadastra outro com X, **então** 409.
- **Dado** o CPF X na Conta B, **quando** a Conta A cadastra X, **então** grava.

## O que ainda não está pronto

### CLI-09 Importar clientes de planilha (pendente)
```

- **IDs são permanentes.** Nunca renumere nem reuse. Uma regra que deixa de valer ganha
  ` (revogada)` no fim do título e continua no doc.
- **O teste aponta para a regra** com `#[Group('CLI-04')]` no método (PHP) ou o ID no nome do
  `describe`/`test` (JS). `composer test -- --group CLI-04` roda tudo o que prova a regra.
- `tests/Spec/RastreabilidadeTest.php` reprova a suíte quando uma regra ativa fica sem cenário ou
  sem teste, quando um teste cita uma regra inexistente ou revogada, e quando um ID se repete ou
  foge do prefixo do doc.
