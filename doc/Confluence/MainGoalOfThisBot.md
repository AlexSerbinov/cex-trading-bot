<!-- Russian Version -->

# Документация проекта Depth-Bot

## Обзор

### Что такое Depth-Bot?

Depth-Bot — это автоматизированный инструмент, разработанный для создания видимости активности и ликвидности на нашей криптовалютной бирже путем наполнения книги ордеров (order book). Его основная задача — имитировать рыночную активность, размещая ордера на покупку и продажу на торговом сервере. Эти ордера становятся доступными для реальных пользователей, позволяя им торговать.

### Зачем он нам нужен?

На новой или низколиквидной бирже пустая книга ордеров может отпугнуть трейдеров. Depth-Bot решает эту проблему следующим образом:

*   **Наполнение книги ордеров:** Он размещает и управляет ордерами на покупку и продажу для различных торговых пар (например, BTC_USDT, ETH_BTC), обеспечивая постоянное наличие ордеров в стакане.
*   **Создание видимости активности:** Когда пользователи видят наполненную книгу ордеров, это создает впечатление активного рынка, что повышает доверие и привлекает реальных трейдеров.
*   **Обеспечение возможности торговли:** Размещая ордера, бот предоставляет ликвидность, с которой могут взаимодействовать пользователи, совершая сделки.

### Основная цель

Главная задача бота — поддерживать видимость активной и ликвидной биржи. Он размещает ордера на торговом сервере, основываясь на текущих рыночных данных и конфигурации (например, размеры ордеров, частота, ценовые отклонения).

#### Как работает Depth-Bot?

1.  **Копирование данных:** Бот получает данные о книге ордеров (цены и объемы на покупку и продажу) из внешнего источника, указанного в конфигурации (например, Binance или Kraken).
2.  **Размещение ордеров:** На основе этих данных бот размещает лимитные ордера на покупку и продажу на нашем торговом сервере.
3.  **Имитация активности:** Чтобы создать видимость динамичного рынка, бот постоянно выполняет цикл операций: отменяет некоторые из своих ранее выставленных ордеров и размещает новые, слегка измененные ордера (с другими ценами или объемами в рамках настроек).
4.  **Результат:** Это создает постоянное движение в книге ордеров, имитируя реальную торговую активность, хотя сам бот не совершает сделок сам с собой. Реальная торговля происходит только тогда, когда пользователь решает исполнить один из ордеров, выставленных ботом.
5.  **Взаимодействие с пользователями:** Когда пользователь исполняет ордер бота, происходит реальная сделка. Благодаря параметрам безопасности, таким как `MarketGap`, эта сделка обычно выгодна для биржи.

#### Механизм безопасности: `MarketGap`

Важной функцией безопасности является параметр `MarketGap`. Этот параметр позволяет настроить дополнительный процентный разрыв (спред) между лучшей ценой покупки (bid) и лучшей ценой продажи (ask), которые выставляет бот, относительно расчетной рыночной цены. Например, если рыночная цена BTC составляет $100,000, а `MarketGap` установлен на 1%, бот может выставить лучшую покупку на уровне $99,000 и лучшую продажу на $101,000 (плюс дополнительные отклонения, заданные `Price Factor`).

Это обеспечивает подушку безопасности: если пользователь совершает сделку с ордером бота, эта сделка уже является локально выгодной для биржи (мы покупаем дешевле или продаем дороже относительно "справедливой" рыночной цены).

### Реализация прибыли

Когда пользователь совершает сделку с ордером, размещенным Depth-Bot (благодаря защитному `MarketGap`), это считается успешной локальной операцией для биржи.

**Текущее состояние:**
На данный момент бот успешно выполняет свою основную функцию — размещение ордеров и обеспечение возможности торговли для пользователей с использованием `MarketGap`.

**Следующие шаги (в разработке):**
Полный цикл фиксации прибыли (например, хеджирование исполненной сделки на внешней бирже, такой как Binance, для окончательной фиксации прибыли) еще не реализован. Сейчас система накапливает результат локальных сделок на балансе бота. Мы работаем над стратегиями и механизмами для реализации полного цикла управления активами и фиксации прибыли.

---

<!-- English Version -->

# Depth-Bot Project Documentation

## Overview

### What is the Depth-Bot?

The Depth-Bot is an automated tool designed to create the appearance of activity and liquidity on our cryptocurrency exchange by populating the order book. Its primary role is to simulate market activity by placing buy and sell orders on the trading server. These orders become available for real users to trade against.

### Why Do We Need It?

On a new or low-liquidity exchange, an empty order book can deter traders. The Depth-Bot addresses this by:

*   **Filling the Order Book:** It places and manages buy and sell orders for various trading pairs (e.g., BTC_USDT, ETH_BTC), ensuring the order book always has orders.
*   **Creating Visible Activity:** When users see a populated order book, it gives the impression of an active market, which builds trust and attracts real traders.
*   **Enabling Trading:** By placing orders, the bot provides liquidity that users can interact with to execute trades.

### Main Goal

The bot's main task is to maintain the appearance of an active and liquid exchange. It places orders on the trading server based on current market data and configuration (e.g., order sizes, frequency, price deviations).

#### How Does Depth-Bot Work?

1.  **Data Copying:** The bot fetches order book data (bid/ask prices and volumes) from an external source specified in the configuration (e.g., Binance or Kraken).
2.  **Order Placement:** Based on this data, the bot places limit buy and sell orders onto our trading server.
3.  **Simulating Activity:** To create the appearance of a dynamic market, the bot continuously cycles through operations: it cancels some of its previously placed orders and places new, slightly modified orders (with different prices or amounts within configured limits).
4.  **Result:** This generates constant movement in the order book, simulating real trading activity, even though the bot doesn't trade with itself. Actual trading only occurs when a user decides to execute one of the orders placed by the bot.
5.  **User Interaction:** When a user executes the bot's order, a real trade happens. Due to safety parameters like `MarketGap`, this trade is typically favorable for the exchange.

#### Safety Mechanism: `MarketGap`

An important safety feature is the `MarketGap` parameter. This parameter allows configuring an additional percentage spread between the best bid and ask prices the bot places, relative to the calculated market price. For example, if the market price of BTC is $100,000 and `MarketGap` is set to 1%, the bot might place its best bid at $99,000 and its best ask at $101,000 (plus further deviations defined by `Price Factor`).

This provides a safety cushion: if a user trades against the bot's order, that trade is already locally profitable for the exchange (we buy lower or sell higher relative to the "fair" market price).

### Profit Realization

When a user executes a trade against an order placed by the Depth-Bot (due to the `MarketGap` buffer), it is considered a successful local operation for the exchange.

**Current Status:**
The bot currently fulfills its primary function: placing orders and enabling user trading with the `MarketGap` safety feature.

**Next Steps (Under Development):**
The full profit realization cycle (e.g., hedging the executed trade on an external exchange like Binance to lock in the profit) is not yet implemented. Currently, the system accumulates the results of these local trades on the bot's balance. We are working on strategies and mechanisms to implement the complete cycle of asset management and profit realization.

