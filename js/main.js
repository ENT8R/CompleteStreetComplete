const plots = [
  'bar',
  'bar-horizontal',
  'pie'
];

const types = [
  'chart',
  'image',
  'translation'
]

function init(data) {
  const containerTemplate = $('#group-template').html();
  Mustache.parse(containerTemplate);

  const elementTemplate = $('#element-template').html();
  Mustache.parse(elementTemplate);

  const modalTemplate = $('#modal-template').html();
  Mustache.parse(modalTemplate);

  for (let key in data) {

    let item = data[key];

    //Get the name, the type and all values
    let name = item.name;
    let type = item.type;
    let values = item.values;

    let containerID = generateID();

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
      $('#images').append(container);
    } else if (type == types[2]) {
      $('#translations').append(container);
    }

    for (let language in values) {
      let singleValues = values[language];

      //All types except image questions
      if (type != types[1]) {
        let labels = [];
        let data = [];

        for (let answer in singleValues) {
          let amount = singleValues[answer];
          labels.push(answer);
          data.push(amount);
        }

        const plotID = generateID();
        const rendered = Mustache.render(elementTemplate, {
          language: language,
          id: plotID
        });

        if (!$('#content').is(":visible")) $('#content').show();

        $('#' + containerID).append(rendered);

        //Initialize a new plot
        Plotly.newPlot(plotID, getPlot(Math.floor(Math.random() * plots.length), labels, data), null, {
          displayModeBar: false
        });
      }
      //Images questions
      else
      {
        const modalID = generateID();
        const contentID = generateID();

        const renderedCard = Mustache.render(elementTemplate, {
          language: language,
          content: '<a href="#' + modalID + '" class="waves-effect waves-light indigo btn modal-trigger">Show images</a>'
        });
        if (!$('#content').is(":visible")) $('#content').show();
        $('#' + containerID).append(renderedCard);

        const renderedModal = Mustache.render(modalTemplate, {
          modalID: modalID,
          contentID: contentID,
          title: name + ' (' + language + ')'
        });
        $('#modals').append(renderedModal);
        $('.modal').modal();

        for (let i = 0; i < singleValues.length; i++) {
          $('#' + contentID).append(getImageHTML(singleValues[i]));
        }
      }
    }
  }
}

function generateID() {
  let S4 = function() {
    return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1);
  };
  return (S4() + S4() + "-" + S4() + "-" + S4() + "-" + S4() + "-" + S4() + S4() + S4());
}

function getPlot(id, labels, data) {
  switch (id) {
    case 0:
      return [{
        x: labels,
        y: data,
        type: 'bar'
      }];
      break;
    case 1:
      return [{
        x: data,
        y: labels,
        type: 'bar',
        orientation: 'h'
      }];
      break;
    case 2:
      return [{
        labels: labels,
        values: data,
        type: 'pie'
      }];
      break;
    default:
      return [{
        x: labels,
        y: data,
        type: 'bar'
      }];
      break;
  }
}

function getImageHTML(url) {
  return '<a href="' + url + '" target="_blank"><img src="' + url + '" width="200px"></a>&nbsp;';
}

$.get('api/get', function(data) {
  init(data);
});
