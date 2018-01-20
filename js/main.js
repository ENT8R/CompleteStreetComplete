const plots = [
  'bar',
  'bar-horizontal',
  'pie'
];

const types = [
  'chart',
  'translation'
]

function init(data) {
  for (let key in data) {

    let item = data[key];

    //Get the name, the type and all values
    let name = item.name;
    let type = item.type;
    let values = item.values;

    let containerID = generateID();

    let containerTemplate = $('#group-template').html();
    Mustache.parse(containerTemplate);
    let container = Mustache.render(containerTemplate, {
      name: name,
      id: containerID
    });

    if (!values || values.length == 0) {
      continue;
    }

    if (type == types[0]) {
      $('#evaluations').append(container);
    } else if (type == types[1]) {
      $('#translations').append(container);
    }

    for (let language in values) {
      let singleValues = values[language];

      let plot;
      let labels = [];
      let data = [];

      for (let answer in singleValues) {
        let amount = singleValues[answer];
        labels.push(answer);
        data.push(amount);
      }

      switch (plots[Math.floor(Math.random() * plots.length)]) {
        case plots[0]:
          plot = [{
            x: labels,
            y: data,
            type: 'bar'
          }];
          break;

        case plots[1]:
          plot = [{
            x: data,
            y: labels,
            type: 'bar',
            orientation: 'h'
          }];
          break;

        case plots[2]:
          plot = [{
            labels: labels,
            values: data,
            type: 'pie'
          }];
          break;
      }

      let plotID = generateID();

      let plotTemplate = $('#plot-template').html();
      Mustache.parse(plotTemplate);
      let rendered = Mustache.render(plotTemplate, {
        title: name + ' (' + language + ')',
        language: language,
        id: plotID
      });

      if (!$('#content').is(":visible")) $('#content').show();

      $('#' + containerID).append(rendered);

      //Initialize a new plot
      Plotly.newPlot(plotID, plot, null, {
        displayModeBar: false
      });
    }
  }
}

function generateID() {
  let S4 = function() {
    return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1);
  };
  return (S4() + S4() + "-" + S4() + "-" + S4() + "-" + S4() + "-" + S4() + S4() + S4());
}

$.get('api/get', function(data) {
  init(data[0]);
});
